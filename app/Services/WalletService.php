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
    public function __construct(protected WalletRepository $walletRepo) {}

    /* ===============================
       HELPERS
    ================================ */

    protected function getSuperAdmin(): User
    {
        $admin = User::role('superadmin')->first();
        if (!$admin) abort(500, 'Super admin not found');
        return $admin;
    }

    protected function ensureSuperAdmin(User $user): void
    {
        if (!$user->hasRole('superadmin')) {
            abort(403, 'Only super admin can perform this action');
        }
    }

    protected function reference(): string
    {
        return (string) Str::uuid();
    }

    /* ===============================
       LOW-LEVEL LEDGER (PROTECTED)
    =============================== */

    public function credit(User $user, float $amount, string $description, string $groupReference): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $groupReference) {
            $wallet = $this->walletRepo->getByUserIdForUpdate($user->id);
            $before = $wallet->balance;
            $after = $before + $amount;
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
        });
    }

    protected function debit(User $user, float $amount, string $description, string $groupReference): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $groupReference) {
            $wallet = $this->walletRepo->getByUserIdForUpdate($user->id);
            if ($wallet->balance < $amount) {
                abort(422, 'Insufficient wallet balance');
            }

            $before = $wallet->balance;
            $after = $before - $amount;
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
        });
    }

    /* ===============================
       SERVICE TRANSFERS (PAIRED) - FIXED
    =============================== */

    public function servicePayment(User $customer, User $platform, float $amount, string $description, string $groupReference = null): array
    {
        $groupRef = $groupReference ?? 'svc_' . now()->format('YmdHis') . '_' . substr($customer->id, -6);

        return DB::transaction(function () use ($customer, $platform, $amount, $description, $groupRef) {
            // 🔒 ATOMIC CHECK
            $exists = DB::table('wallet_transactions')
                ->where('group_reference', $groupRef)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                abort(409, 'Duplicate transaction group detected');
            }

            $debitTx  = $this->debit($customer, $amount, $description . ' (Debit)', $groupRef);
            $creditTx = $this->credit($platform, $amount, $description . ' (Credit)', $groupRef);

            return [
                'debit_transaction'  => $debitTx,
                'credit_transaction' => $creditTx,
                'group_reference'    => $groupRef
            ];
        });
    }

    /* ===============================
       PUBLIC METHODS
    =============================== */

    public function creditUser(User $user, float $amount, string $description, string $groupReference = null): WalletTransaction
    {
        $groupReference = $groupReference ?? $this->reference();
        return DB::transaction(function () use ($user, $amount, $description, $groupReference) {
            $exists = DB::table('wallet_transactions')
                ->where('group_reference', $groupReference)
                ->lockForUpdate()
                ->exists();
            if ($exists) abort(409, 'Duplicate transaction group detected');
            return $this->credit($user, $amount, $description, $groupReference);
        });
    }

    public function debitUser(User $user, float $amount, string $description, string $groupReference = null): WalletTransaction
    {
        $groupReference = $groupReference ?? $this->reference();
        return DB::transaction(function () use ($user, $amount, $description, $groupReference) {
            $exists = DB::table('wallet_transactions')
                ->where('group_reference', $groupReference)
                ->lockForUpdate()
                ->exists();
            if ($exists) abort(409, 'Duplicate transaction group detected');
            return $this->debit($user, $amount, $description, $groupReference);
        });
    }

    public function transfer(User $from, User $to, float $amount, string $description, string $groupReference = null): array
    {
        $groupRef = $groupReference ?? $this->reference();
        return DB::transaction(function () use ($from, $to, $amount, $description, $groupRef) {
            $exists = DB::table('wallet_transactions')
                ->where('group_reference', $groupRef)
                ->lockForUpdate()
                ->exists();
            if ($exists) abort(409, 'Duplicate transaction group detected');

            $debitTx  = $this->debit($from, $amount, $description . ' (Sent)', $groupRef);
            $creditTx = $this->credit($to, $amount, $description . ' (Received)', $groupRef);

            return [
                'debit_transaction'  => $debitTx,
                'credit_transaction' => $creditTx,
                'group_reference'    => $groupRef
            ];
        });
    }

    /* ===============================
       ADMIN OPERATIONS - BULLETPROOF FIXED
    =============================== */

    public function adminCreditUser(User $admin, User $user, float $amount, string $reason): void
    {
        $this->ensureSuperAdmin($admin);
        $superAdmin = $this->getSuperAdmin();
        $ref = 'ADMIN_CREDIT_' . now()->timestamp . '_' . Str::random(8); // ✅ LONGER UNIQUE

        DB::transaction(function () use ($superAdmin, $user, $amount, $reason, $ref) {
            // 🔒 ATOMIC CHECK FOR ADMIN TX
            $exists = DB::table('wallet_transactions')
                ->where('group_reference', $ref)
                ->lockForUpdate()
                ->exists();
            if ($exists) abort(409, 'Duplicate admin transaction detected');

            $this->debit($superAdmin, $amount, "Funding user ({$user->email}): {$reason}", $ref);
            $creditTx = $this->credit($user, $amount, "Wallet funded by admin: {$reason}", $ref);

            Mail::to($user->email)->send(new WalletCredited($user, $amount, $creditTx->balance_after, $reason));
        });
    }

    public function adminDebitUser(User $admin, User $user, float $amount, string $reason): void
    {
        $this->ensureSuperAdmin($admin);
        $superAdmin = $this->getSuperAdmin();
        $ref = 'ADMIN_DEBIT_' . now()->timestamp . '_' . Str::random(8); // ✅ LONGER UNIQUE

        DB::transaction(function () use ($superAdmin, $user, $amount, $reason, $ref) {
            // 🔒 ATOMIC CHECK FOR ADMIN TX
            $exists = DB::table('wallet_transactions')
                ->where('group_reference', $ref)
                ->lockForUpdate()
                ->exists();
            if ($exists) abort(409, 'Duplicate admin transaction detected');

            $debitTx = $this->debit($user, $amount, "Admin debit: {$reason}", $ref);
            $this->credit($superAdmin, $amount, "Collected from user ({$user->email}): {$reason}", $ref);

            Mail::to($user->email)->send(new WalletDebited($user, $amount, $debitTx->balance_after, $reason));
        });
    }

    /* ===============================
       READ OPERATIONS
    =============================== */

    public function transactions(User $user, int $perPage = 20): array
    {
        $wallet = $this->walletRepo->getByUserId($user->id);
        return [
            'current_balance' => $wallet->balance,
            'transactions'    => $wallet->transactions()->latest()->paginate($perPage)
        ];
    }

    public function getWallet(User $user): array
    {
        $wallet = $this->walletRepo->getByUserId($user->id);
        return [
            'id'         => $wallet->id,
            'balance'    => $wallet->balance,
            'currency'   => $wallet->currency ?? 'NGN',
            'created_at' => $wallet->created_at,
        ];
    }

    /* ===============================
       UTILITIES
    =============================== */

    public function ensureUniqueGroup(string $groupReference): void
    {
        if (WalletTransaction::where('group_reference', $groupReference)->exists()) {
            abort(409, 'Duplicate transaction group detected');
        }
    }

    public function transactionExists(string $groupReference): bool
    {
        return WalletTransaction::where('group_reference', $groupReference)->exists();
    }
}
