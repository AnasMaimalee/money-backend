<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Services\AdminManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminSetPasswordMail;
use App\Models\User;
use Illuminate\Support\Str;
class AdminManagementController extends Controller
{
    public function __construct(protected AdminManagementService $service)
    {
    }

    /**
     * List all administrators
     */
    public function index(): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $admins = $this->service->getAdministrators(paginated: true, perPage: 20);

        return response()->json([
            'message' => 'Administrators retrieved successfully',
            'data' => $admins,
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'state' => 'required|string',
        ]);

        $tempPassword = Str::random(32);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'state'    => $request->state,
            'password' => Hash::make($tempPassword),
        ]);

        $user->assignRole('administrator');

        // 🔐 Send set-password link
        Password::sendResetLink([
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Administrator created. Password setup link sent to email.',
            'user' => $user->load('wallet'),
        ], 201);
    }


    /**
     * Soft delete administrator
     */
    public function destroy($adminId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $admin = $this->service->findAdministratorById($adminId);

        $result = $this->service->deleteAdministrator($admin);

        return response()->json($result);
    }

    /**
     * Restore deleted administrator
     */
    public function restore($id): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $result = $this->service->restoreAdministrator($id);

        return response()->json($result);
    }

    public function banks()
    {
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get('https://api.paystack.co/bank');

        return response()->json($response->json()['data']);
    }
}
