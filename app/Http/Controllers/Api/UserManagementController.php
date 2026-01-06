<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserManagementController extends Controller
{
    public function __construct(protected UserManagementService $service)
    {
    }

    /**
     * List all regular users (with optional search)
     */

    /**
     * Manually debit user wallet
     */
    public function debitWallet(Request $request, string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255'
        ]);

        $user = $this->service->findUserById($userId);

        $result = $this->service->manuallyDebitWallet(
            $user,
            $request->amount,
            $request->reason ?? 'Manual debit by superadmin'
        );

        return response()->json($result);
    }

    /**
     * View user wallet transaction history
     */
    public function transactions(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $result = $this->service->getWalletTransactions($userId);

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $search = $request->query('search');
        $users = $this->service->getUsers($search);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    /**
     * Manually fund user wallet (emergency deposit)
     */
    public function fundWallet(Request $request, string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255'
        ]);

        $user = $this->service->findUserById($userId);

        $result = $this->service->manuallyCreditWallet(
            $user,
            $request->amount,
            $request->reason ?? 'Manual funding by superadmin'
        );

        return response()->json($result, 200);
    }

    /**
     * Soft delete user
     */
    public function destroy(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $user = $this->service->findUserById($userId);

        $result = $this->service->softDeleteUser($user);

        return response()->json($result);
    }

    /**
     * Restore soft-deleted user
     */
    public function restore(string $id): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $result = $this->service->restoreUser($id);

        return response()->json($result);
    }

    /**
     * Show single user details
     */
    public function show(string $userId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $user = $this->service->findUserById($userId);

        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => $user->load('wallet')
        ]);
    }
}
