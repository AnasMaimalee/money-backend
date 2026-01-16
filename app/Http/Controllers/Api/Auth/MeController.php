<?php

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminAccountCreated;
use Illuminate\Support\Str;
use App\Models\LoginAudit;
use Illuminate\Support\Facades\Http;

class MeController extends Controller
{
    // REGISTER
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string',
            'state' => 'required|string',
            'password' => 'sometimes|string|min:6'
        ]);

        $password = $request->password ?? Str::random(8);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'state' => $request->state,
            'password' => bcrypt($password),
        ]);

        // assign default role
        $user->assignRole('user');

        return response()->json([
            'message' => 'User registered',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            // Log failed login
            $this->logLoginAttempt(null, $request, false);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();

        // Log successful login
        $this->logLoginAttempt($user, $request, true);

        return response()->json([
            'token' => $token,
            'me' => $this->formatUser($user)
        ]);
    }


    /**
     * Logout user (invalidate JWT token)
     */
    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            try {
                JWTAuth::invalidate(JWTAuth::getToken());
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return response()->json([
                    'message' => 'Failed to logout, please try again.'
                ], 500);
            }

            return response()->json([
                'message' => 'User logged out successfully'
            ]);
        }

        return response()->json([
            'message' => 'No user logged in'
        ], 401);
    }
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed', // password_confirmation
        ]);

        $user = auth()->user();

        // Verify current password
        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        // Update password
        $user->password = bcrypt($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }


    // CREATE ADMINISTRATOR
    public function createAdministrator(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'state' => 'required|string',
        ]);

        $password = Str::random(8);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'state' => $request->state,
            'password' => bcrypt($password),
        ]);

        $user->assignRole('administrator');

        // Wallet is automatically created via model boot()

        Mail::to($user->email)->send(new AdminAccountCreated($user, $password));

        return response()->json([
            'message' => 'Administrator created successfully',
            'user' => $this->formatUser($user->load('wallet')), // optional: include wallet
        ]);
    }

    private function logLoginAttempt($user, Request $request, bool $success)
    {
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');

        // Optional: get location from IP
        $location = null;
        try {
            $geo = Http::get("http://ip-api.com/json/{$ip}")->json();
            if ($geo['status'] === 'success') {
                $location = $geo['city'] . ', ' . $geo['country'];
            }
        } catch (\Exception $e) {
            $location = null;
        }

        LoginAudit::create([
            'user_id'    => $user?->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'location'   => $location,
            'success'    => $success,
        ]);
    }

    // ME endpoint
    public function me()
    {
        $user = auth()->user(); // might be null
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->load(['wallet']);

        return response()->json([
            'user' => $user,
            'role' => $user->getRoleNames()->first() ?? 'user',
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'wallet' => [
                'balance' => $user->wallet?->balance ?? 0,
            ],
            'menus' => $this->getMenusForUser($user),
        ]);
    }


    // Format user data for API
    private function formatUser(User $user)
    {
        $user->load(['wallet']);

        return [
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'wallet' => [
                'balance' => $user->wallet?->balance ?? 0,
            ],
            'menus' => $this->getMenusForUser($user),
        ];
    }


    // Define menu items per role
    private function getMenusForUser(User $user)
    {
        $menus = [
            'user' => [
                [
                    'label' => 'Dashboard',
                    'route' => '/dashboard/user',
                    'icon' => 'DashboardOutlined',
                ],
                [
                    'label' => 'Wallet',
                    'route' => '/user/wallet',
                    'icon' => 'WalletOutlined',
                ],
                [
                    'label' => 'CBT Practice',
                    'icon' => 'FileTextOutlined',
                    'children' => [
                        [
                            'label' => 'Start Practice',
                            'route' => '/user/cbt',
                            'icon' => 'PlayCircleOutlined',
                        ],
                        [
                            'label' => 'My Results',
                            'route' => '/user/cbt/results',
                            'icon' => 'BarChartOutlined',
                        ],
                    ],
                ],
                [
                    'label' => 'Price Services',
                    'route' => '/user/services',
                    'icon' => 'SettingOutlined',
                ],
                [
                    'label' => 'JAMB Result',
                    'route' => '/user/services/jamb-result',
                    'icon' => 'AppstoreOutlined',
                ],
                [
                    'label' => 'JAMB Admission Letter',
                    'route' => '/user/services/jamb-admission-letter',
                    'icon' => 'FileTextOutlined',
                ],
                [
                    'label' => 'JAMB Olevel Status',
                    'route' => '/user/services/jamb-olevel-status',
                    'icon' => 'ProfileOutlined',
                ],
                [
                    'label' => 'Admission Status Checking',
                    'route' => '/user/services/jamb-admission-status-checking',
                    'icon' => 'CheckCircleOutlined',
                ],
                [
                    'label' => 'JAMB Result Notification',
                    'route' => '/user/services/jamb-result-notification',
                    'icon' => 'BellOutlined',
                ],
                [
                    'label' => 'JAMB PIN Binding',
                    'route' => '/user/services/jamb-pin-binding',
                    'icon' => 'BellOutlined',
                ],
                [
                    'label' => 'Settings',
                    'route' => '/user/setting',
                    'icon' => 'SettingOutlined',
                ],
            ],
            'administrator' => [
                ['label' => 'Dashboard', 'route' => '/dashboard/administrator', 'icon' => 'DashboardOutlined'],
                ['label' => 'Wallet', 'route' => '/administrator/wallet', 'icon' => 'WalletOutlined'],
                ['label' => 'Payout', 'route' => '/administrator/payout', 'icon' => 'BankOutlined'],
                ['label' => 'Price Services', 'route' => '/administrator/price-services', 'icon' => 'SettingOutlined'],
                ['label' => 'JAMB Result', 'route' => '/administrator/services/jamb-result', 'icon' => 'AppstoreOutlined'],
                ['label' => 'JAMB Admission Letter', 'route' => '/administrator/services/jamb-admission-letter', 'icon' => 'FileTextOutlined'],
                ['label' => 'JAMB Olevel Status', 'route' => '/administrator/services/jamb-olevel-status', 'icon' => 'ProfileOutlined'],
                ['label' => 'Admission Status Checking', 'route' => '/administrator/services/jamb-check-admission', 'icon' => 'CheckCircleOutlined'],
                ['label' => 'JAMB Result Notification', 'route' => '/administrator/services/jamb-result-notification', 'icon' => 'BellOutlined'],
                ['label' => 'JAMB PIN Binding', 'route' => '/administrator/services/jamb-pin-binding', 'icon' => 'BellOutlined',],
                ['label' => 'Setting', 'route' => '/administrator/setting', 'icon' => 'SettingOutlined'],
            ],
            'superadmin' => [
                [
                    'label' => 'Dashboard',
                    'route' => '/dashboard/superadmin',
                    'icon' => 'DashboardOutlined',
                ],
                [
                    'label' => 'Wallet',
                    'route' => '/superadmin/wallet',
                    'icon' => 'WalletOutlined',
                ],
                [
                    'label' => 'Payouts',
                    'route' => '/superadmin/payouts',
                    'icon' => 'BankOutlined',
                ],
                [
                    'label' => 'CBT Management',
                    'icon' => 'FileTextOutlined',
                    'children' => [
                        [
                            'label' => 'Subjects',
                            'route' => '/superadmin/cbt/subjects',
                            'icon' => 'BookOutlined',
                        ],
                        [
                            'label' => 'Questions',
                            'route' => '/superadmin/cbt/questions',
                            'icon' => 'FormOutlined',
                        ],
                        [
                            'label' => 'Exams',
                            'route' => '/superadmin/cbt/exams',
                            'icon' => 'ClockCircleOutlined',
                        ],
                        [
                            'label' => 'Analytics',
                            'route' => '/superadmin/cbt/analytics',
                            'icon' => 'BarChartOutlined',
                        ],
                        [
                            'label' => 'Leader Board',
                            'route' => '/superadmin/cbt/leader-board',
                            'icon' => 'TrophyOutlined',
                        ],
                        [
                            'label' => 'CBT Setting',
                            'route' => '/superadmin/cbt/cbt-setting',
                            'icon' => 'SettingOutlined',
                        ],
                    ],
                ],
                [
                    'label' => 'Manage Users',
                    'route' => '/superadmin/users',
                    'icon' => 'UserOutlined',
                ],
                [
                    'label' => 'Manage Services',
                    'route' => '/superadmin/services',
                    'icon' => 'SettingOutlined',
                ],
                [
                    'label' => 'JAMB Original Result',
                    'route' => '/superadmin/services/jamb-result',
                    'icon' => 'FileSearchOutlined',
                ],
                [
                    'label' => 'JAMB Admission Letter',
                    'route' => '/superadmin/services/jamb-admission-letter',
                    'icon' => 'FileTextOutlined',
                ],
                [
                    'label' => 'JAMB Olevel Status',
                    'route' => '/superadmin/services/jamb-olevel-status',
                    'icon' => 'IdcardOutlined',
                ],
                [
                    'label' => 'Admission Checking Status',
                    'route' => '/superadmin/services/jamb-check-admission',
                    'icon' => 'CheckCircleOutlined',
                ],
                [
                    'label' => 'JAMB Result Notification',
                    'route' => '/superadmin/services/jamb-result-notification',
                    'icon' => 'BellOutlined',
                ],
                [
                    'label' => 'JAMB PIN Binding',
                    'route' => '/superadmin/services/jamb-pin-binding',
                    'icon' => 'BellOutlined',
                ],
                [
                    'label' => 'Settings',
                    'route' => '/superadmin/setting',
                    'icon' => 'SettingOutlined',
                ],
            ],
        ];

        return $menus[$user->getRoleNames()->first()] ?? [];
    }


}
