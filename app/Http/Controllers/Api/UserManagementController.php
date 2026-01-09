<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $service
    ) {}

    /**
     * List users (with optional search)
     */
    public function index(Request $request): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $users = $this->service->getUsers(
            $request->query('search'),
            $request->query('role'),
            $request->boolean('trashed', false),
            $request->get('per_page', 20)
        );


        return response()->json([
            'message' => 'Users retrieved successfully',
            'data'    => $users,
        ]);
    }

    /**
     * Create user or administrator
     */
    public function store(Request $request): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'state' => 'required|string',
            'role'  => 'required|in:user,administrator',
        ]);

        // 🔹 Create user via service
        $user = $this->service->createUser($validated);

        return response()->json([
            'message' => ucfirst($validated['role']) . ' created. Password setup link sent to email.',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'state' => $user->state,
                'role'  => $user->roles->pluck('name')->first(), // ✅ Now it will show
                'wallet'=> $user->wallet,
            ],
        ], 201);
    }


    /**
     * Show single user
     */
    public function show(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        return response()->json([
            'message' => 'User retrieved successfully',
            'data'    => $this->service->findUserById($userId),
        ]);
    }

    /**
     * Soft delete user
     */
    public function destroy(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        return response()->json(
            $this->service->softDeleteUser(
                $this->service->findUserById($userId)
            )
        );
    }

    /**
     * Restore user
     */
    public function restore(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        return response()->json(
            $this->service->restoreUser($userId)
        );
    }

    // ✅ FORCE DELETE (permanent)
    public function forceDelete($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->forceDelete(); // ✅ Permanent delete

        return response()->json(['message' => 'User permanently deleted']);
    }

    // ✅ GET TRASHED USERS (for admin table)
    public function trashed()
    {
        $trashedUsers = User::onlyTrashed()->get();
        return response()->json($trashedUsers);
    }
    /**
     * Manually fund wallet
     */
    public function fundWallet(Request $request, string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        return response()->json(
            $this->service->manuallyCreditWallet(
                $this->service->findUserById($userId),
                $validated['amount'],
                $validated['reason'] ?? 'Manual funding by superadmin'
            )
        );
    }

    /**
     * Manually debit wallet
     */
    public function debitWallet(Request $request, string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        return response()->json(
            $this->service->manuallyDebitWallet(
                $this->service->findUserById($userId),
                $validated['amount'],
                $validated['reason'] ?? 'Manual debit by superadmin'
            )
        );
    }

    /**
     * Wallet transaction history
     */
    public function transactions(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        return response()->json(
            $this->service->getWalletTransactions($userId)
        );
    }
}
