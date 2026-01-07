<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserManagementRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserManagementService
{
    public function __construct(
        protected UserManagementRepository $repository,
        protected WalletService $walletService
    ) {}

    /* ===============================
        SECURITY
    ================================ */

    public function ensureSuperadmin(): void
    {
        if (! auth()->user()?->hasRole('superadmin')) {
            abort(403, 'Only superadmin can manage users.');
        }
    }

    /* ===============================
        USER CREATION
    ================================ */

    /**
     * Create user or administrator
     * Sends password setup email
     */
    public function createUser(array $data): User
    {
        $this->ensureSuperadmin();

        $role = $data['role'] === 'administrator' ? 'administrator' : 'user';

        $tempPassword = Str::random(32);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'],
            'state'    => $data['state'],
            'password' => Hash::make($tempPassword),
        ]);

        // Remove any roles and assign exactly one
        $user->syncRoles([]);
        $user->assignRole($role);

        // Send password setup email
        Password::sendResetLink(['email' => $user->email]);

        // Reload roles & wallet for controller
        return $user->load(['roles', 'wallet']);
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

    public function findUserById(string $id): User
    {
        return $this->repository->findById($id);
    }

    public function softDeleteUser(User $user): array
    {
        $user->delete();

        return [
            'message' => 'User soft deleted successfully',
            'data'    => $user->fresh(),
        ];
    }

    public function restoreUser(string $id): array
    {
        $user = $this->repository->findTrashedById($id);
        $user->restore();

        return [
            'message' => 'User restored successfully',
            'data'    => $user->fresh()->load('wallet'),
        ];
    }

    /* ===============================
        WALLET OPERATIONS (SUPERADMIN)
    ================================ */

    /**
     * Manually credit user wallet
     * Money comes FROM superadmin wallet
     */
    public function manuallyCreditWallet(
        User $user,
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
            'message' => 'Wallet credited successfully',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone']),
                'credited_amount' => $amount,
                'new_balance' => $user->wallet->balance,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * Manually debit user wallet
     * Money goes TO superadmin wallet
     */
    public function manuallyDebitWallet(
        User $user,
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
            'message' => 'Wallet debited successfully',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone']),
                'debited_amount' => $amount,
                'new_balance' => $user->wallet->balance,
                'reason' => $reason,
            ],
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
            ],
        ];
    }
}
