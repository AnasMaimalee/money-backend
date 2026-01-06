<?php

namespace App\Services;

use App\Repositories\UserManagementRepository;
use Illuminate\Http\JsonResponse;

class UserManagementService
{
    public function __construct(protected UserManagementRepository $repository)
    {
    }

    /**
     * Manually debit user wallet
     */
    public function manuallyDebitWallet($user, float $amount, string $reason = 'Manual debit by superadmin'): array
    {
        if ($amount <= 0) {
            abort(422, 'Amount must be greater than zero.');
        }

        if ($user->wallet->balance < $amount * 100) {
            abort(422, 'Insufficient wallet balance for debit.');
        }

        app('wallet')->debit($user, $amount * 100, $reason);

        $user->refresh();

        return [
            'message' => "Wallet debited successfully by ₦" . number_format($amount, 2),
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone']),
                'new_balance' => $user->wallet->balance / 100,
                'debited_amount' => $amount,
                'reason' => $reason,
            ]
        ];
    }

    /**
     * Get wallet transaction history
     */
    public function getWalletTransactions(string $userId, int $perPage = 20)
    {
        $user = $this->findUserById($userId);

        $transactions = $user->wallet->transactions()
            ->latest()
            ->paginate($perPage);

        return [
            'message' => 'Wallet transactions retrieved successfully',
            'data' => [
                'user' => $user->only(['id', 'name', 'email']),
                'current_balance' => $user->wallet->balance / 100,
                'transactions' => $transactions,
            ]
        ];
    }

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

    public function manuallyCreditWallet($user, float $amount, string $reason = 'Manual funding by superadmin'): array
    {
        if ($amount <= 0) {
            abort(422, 'Amount must be greater than zero.');
        }

        app('wallet')->credit($user, $amount * 100, $reason);

        $user->refresh();

        return [
            'message' => "Wallet funded successfully with ₦" . number_format($amount, 2),
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone']),
                'new_balance' => $user->wallet->balance / 100,
                'credited_amount' => $amount,
                'reason' => $reason,
            ]
        ];
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

    public function ensureSuperadmin(): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only superadmin can manage users.');
        }
    }
}
