<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminAccountCreated;
use Tymon\JWTAuth\Facades\JWTAuth;
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

    // LOGIN
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
        $user = auth()->user();
        return response()->json($this->formatUser($user));
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
                ['name' => 'Dashboard', 'route' => '/user/dashboard', 'icon' => 'DashboardOutlined'],
                ['name' => 'Home', 'route' => '/', 'icon' => 'HomeOutlined'],
                ['name' => 'JAMB Services', 'route' => '/services/jamb-result', 'icon' => 'AppstoreOutlined'],
                ['name' => 'JAMB Admission Letter', 'route' => '/services/admission-letter', 'icon' => 'FileTextOutlined'],
                ['name' => 'JAMB Olevel Status', 'route' => '/services/olevel-status', 'icon' => 'ProfileOutlined'],
                ['name' => 'Admission Letter Checking', 'route' => '/services/check-admission', 'icon' => 'CheckCircleOutlined'],
                ['name' => 'JAMB Result Notification', 'route' => '/services/result-notification', 'icon' => 'NotificationOutlined'],
            ],
            'administrator' => [
                ['name' => 'Dashboard', 'route' => '/admin/dashboard', 'icon' => 'DashboardOutlined'],

                // JAMB Services for Admin (different routes from user)
                ['name' => 'JAMB Services', 'route' => '/admin/services/jamb-result', 'icon' => 'AppstoreOutlined'],
                ['name' => 'JAMB Admission Letter', 'route' => '/admin/services/admission-letter', 'icon' => 'FileTextOutlined'],
                ['name' => 'JAMB Olevel Status', 'route' => '/admin/services/olevel-status', 'icon' => 'ProfileOutlined'],
                ['name' => 'Admission Letter Checking', 'route' => '/admin/services/check-admission', 'icon' => 'CheckCircleOutlined'],
                ['name' => 'JAMB Result Notification', 'route' => '/admin/services/result-notification', 'icon' => 'NotificationOutlined'],
            ],
            'superadmin' => [
                ['name' => 'Dashboard', 'route' => '/super/dashboard', 'icon' => 'DashboardOutlined'],

                // Management menus
                ['name' => 'Manage Users', 'route' => '/super/users', 'icon' => 'UserOutlined'],
                ['name' => 'Manage Administrators', 'route' => '/super/admins', 'icon' => 'UserSwitchOutlined'],
                ['name' => 'Manage Services', 'route' => '/super/services', 'icon' => 'AppstoreAddOutlined'],
                ['name' => 'Pricing', 'route' => '/super/pricing', 'icon' => 'DollarOutlined'],

                // JAMB Services for Superadmin (different routes)
                ['name' => 'JAMB Services', 'route' => '/super/services/jamb-result', 'icon' => 'AppstoreOutlined'],
                ['name' => 'JAMB Admission Letter', 'route' => '/super/services/admission-letter', 'icon' => 'FileTextOutlined'],
                ['name' => 'JAMB Olevel Status', 'route' => '/super/services/olevel-status', 'icon' => 'ProfileOutlined'],
                ['name' => 'Admission Letter Checking', 'route' => '/super/services/check-admission', 'icon' => 'CheckCircleOutlined'],
                ['name' => 'JAMB Result Notification', 'route' => '/super/services/result-notification', 'icon' => 'NotificationOutlined'],

                // Superadmin specific JAMB requests
                ['name' => 'All JAMB Requests', 'route' => '/super/jamb-requests', 'icon' => 'FileDoneOutlined'],
            ],

        ];

        return $menus[$user->getRoleNames()->first()] ?? [];
    }
}
