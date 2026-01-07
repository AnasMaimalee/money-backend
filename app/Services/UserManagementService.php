<?php

namespace App\Services;

use App\Repositories\UserManagementRepository;

class UserManagementService
{
    public function __construct(
        protected UserManagementRepository $repository,
        protected WalletService $walletService
    ) {}

    /* ===============================
        WALLET OPERATIONS (ADMIN ONLY)
    ================================ */

    /**
     * Manually credit user wallet
     * Money comes FROM super admin wallet
     */
    public function manuallyCreditWallet(
        $user,
        float $amount,
        string $reason = 'Manual funding by superadmin'
    ): array {
        $this->ensureSuperadmin();

        if ($amount <= 0) {
            abort(422, 'Amount must be greater than zero.');
        }

        $this->walletService->adminCreditUser(
            auth()->user(),
            $user,
            $amount,
            $reason
        );

        $user->refresh();

        return [
            'message' => "Wallet credited successfully",
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone']),
                'credited_amount' => $amount,
                'new_balance' => $user->wallet->balance,
                'reason' => $reason,
            ]
        ];
    }

    /**
     * Manually debit user wallet
     * Money goes TO super admin wallet
     */
    public function manuallyDebitWallet(
        $user,
        float $amount,
        string $reason = 'Manual debit by superadmin'
    ): array {
        $this->ensureSuperadmin();

        if ($amount <= 0) {
            abort(422, 'Amount must be greater than zero.');
        }

        $this->walletService->adminDebitUser(
            auth()->user(),
            $user,
            $amount,
            $reason
        );

        $user->refresh();

        return [
            'message' => "Wallet debited successfully",
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone']),
                'debited_amount' => $amount,
                'new_balance' => $user->wallet->balance,
                'reason' => $reason,
            ]
        ];
    }

    /* ===============================
        WALLET TRANSACTIONS
    ================================ */

    public function getWalletTransactions(string $userId, int $perPage = 20): array
    {
        $this->ensureSuperadmin();

        $user = $this->findUserById($userId);

        $transactions = $user->wallet
            ->transactions()
            ->latest()
            ->paginate($perPage);

        return [
            'message' => 'Wallet transactions retrieved successfully',
            'data' => [
                'user' => $user->only(['id', 'name', 'email']),
                'current_balance' => $user->wallet->balance,
                'transactions' => $transactions,
            ]
        ];
    }

    /* ===============================
        USER MANAGEMENT
    ================================ */

    public function getUsers(?string $search = null, int $perPage = 20)
    {
        return $search
            ? $this->repository->search($search, $perPage)
            : $this->repository->getPaginatedUsers($perPage);
    }

    public function findUserById(string $id)
    {
        return $this->repository->findById($id);
    }

    public function softDeleteUser($user): array
    {
        $user->delete();

        return [
            'message' => 'User soft deleted successfully',
            'data' => $user->fresh()
        ];
    }

    public function restoreUser(string $id): array
    {
        $user = $this->repository->findTrashedById($id);
        $user->restore();

        return [
            'message' => 'User restored successfully',
            'data' => $user->fresh()->load('wallet')
        ];
    }

    /* ===============================
        SECURITY
    ================================ */

    public function ensureSuperadmin(): void
    {
        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only superadmin can manage users.');
        }
    }
}
