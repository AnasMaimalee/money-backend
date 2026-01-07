<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WalletCredited;
use App\Mail\WalletDebited;

class WalletService
{
    public function __construct(
        protected WalletRepository $walletRepo
    ) {}

    /* ===============================
        HELPERS
    ================================ */

    protected function getSuperAdmin(): User
    {
        $admin = User::role('superadmin')->first();

        if (! $admin) {
            abort(500, 'Super admin not found');
        }

        return $admin;
    }

    protected function ensureSuperAdmin(User $user): void
    {
        if (! $user->hasRole('superadmin')) {
            abort(403, 'Only super admin can perform this action');
        }
    }

    protected function reference(): string
    {
        return (string) Str::uuid();
    }

    /* ===============================
        LEDGER (NO EMAILS HERE ❌)
    ================================ */

    protected function credit(
        User $user,
        float $amount,
        string $description,
        string $groupReference
    ): WalletTransaction {
        $wallet = $this->walletRepo->getByUserId($user->id);

        $before = $wallet->balance;
        $after  = $before + $amount;

        $this->walletRepo->updateBalance($wallet, $after);

        return $this->walletRepo->createTransaction([
            'user_id'         => $user->id,
            'wallet_id'       => $wallet->id,
            'type'            => 'credit',
            'amount'          => $amount,
            'balance_before'  => $before,
            'balance_after'   => $after,
            'reference'       => (string) Str::uuid(),
            'group_reference' => $groupReference,
            'description'     => $description,
        ]);
    }

    protected function debit(
        User $user,
        float $amount,
        string $description,
        string $groupReference
    ): WalletTransaction {
        $wallet = $this->walletRepo->getByUserId($user->id);

        if ($wallet->balance < $amount) {
            abort(422, 'Insufficient wallet balance');
        }

        $before = $wallet->balance;
        $after  = $before - $amount;

        $this->walletRepo->updateBalance($wallet, $after);

        return $this->walletRepo->createTransaction([
            'user_id'         => $user->id,
            'wallet_id'       => $wallet->id,
            'type'            => 'debit',
            'amount'          => $amount,
            'balance_before'  => $before,
            'balance_after'   => $after,
            'reference'       => (string) Str::uuid(),
            'group_reference' => $groupReference,
            'description'     => $description,
        ]);
    }

    /* ===============================
        ADMIN OPERATIONS (EMAILS ✅)
    ================================ */
    public function adminCreditUser(
        User $admin,
        User $user,
        float $amount,
        string $reason // <-- collected from form
    ): void {
        $this->ensureSuperAdmin($admin);

        $superAdmin = $this->getSuperAdmin();
        $ref = $this->reference();

        $creditTx = null;

        DB::transaction(function () use ($superAdmin, $user, $amount, $reason, $ref, &$creditTx) {
            // Super admin pays
            $this->debit(
                $superAdmin,
                $amount,
                "Admin funding user ({$user->email}): {$reason}",
                $ref
            );

            // User receives
            $creditTx = $this->credit(
                $user,
                $amount,
                "Wallet funded by admin: {$reason}",
                $ref
            );
        });

        // ✅ Send email using actual transaction data
        if ($creditTx) {
            Mail::to($user->email)->send(
                new WalletCredited(
                    $user,
                    $amount,
                    $creditTx->balance_after, // accurate updated balance
                    $reason // <-- dynamic from form
                )
            );
        }
    }



    public function adminDebitUser(
        User $admin,
        User $user,
        float $amount,
        string $reason
    ): void {
        $this->ensureSuperAdmin($admin);

        $superAdmin = $this->getSuperAdmin();
        $ref = $this->reference();

        DB::transaction(function () use ($superAdmin, $user, $amount, $reason, $ref) {

            // User pays
            $debitTx = $this->debit(
                $user,
                $amount,
                "Admin debit: {$reason}",
                $ref
            );

            // Super admin receives
            $this->credit(
                $superAdmin,
                $amount,
                "Collected from user ({$user->email}): {$reason}",
                $ref
            );

            // ✅ EMAIL (FIXED — 4 ARGUMENTS)
            Mail::to($user->email)->send(
                new WalletDebited(
                    $user,
                    $amount,
                    $debitTx->balance_after,
                    $reason
                )
            );

        });
    }

    /* ===============================
        READ OPERATIONS
    ================================ */

    public function transactions(User $user, int $perPage = 20): array
    {
        $wallet = $this->walletRepo->getByUserId($user->id);

        return [
            'current_balance' => $wallet->balance,
            'transactions' => $wallet->transactions()
                ->latest()
                ->paginate($perPage)
        ];
    }
}
