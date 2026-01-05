<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\WalletCredited;
use App\Mail\WalletDebited;

class WalletService
{
    public function __construct(
        protected WalletRepository $walletRepo
    ) {}

    /**
     * Get the user's wallet
     */
    public function getWallet(User $user): array
    {
        $wallet = $this->walletRepo->getByUserId($user->id);

        return [
            'balance' => $wallet->balance,
        ];
    }

    /**
     * Get wallet transactions for a user
     */
    public function transactions(User $user)
    {
        return $this->walletRepo
            ->getByUserId($user->id)
            ->transactions()
            ->latest()
            ->get();
    }

    /**
     * Credit user's wallet
     */
    public function credit(User $user, float $amount, string $description, ?string $reference = null): WalletTransaction
    {
        $reference ??= (string) Str::uuid();

        // Prevent double credit
        if (WalletTransaction::where('reference', $reference)->exists()) {
            abort(422, 'Transaction with this reference already exists');
        }

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $wallet = $this->walletRepo->getByUserId($user->id);

            $before = $wallet->balance;
            $after = $before + $amount;

            $this->walletRepo->updateBalance($wallet, $after);

            $transaction = $this->walletRepo->createTransaction([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference,
                'description' => $description,
            ]);

            // Send email notification
            Mail::to($user->email)->send(new WalletCredited($user, $amount, $after));

            return $transaction;
        });
    }

    /**
     * Debit user's wallet
     */
    public function debit(User $user, float $amount, string $description, ?string $reference = null): WalletTransaction
    {
        $reference ??= (string) Str::uuid();

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $wallet = $this->walletRepo->getByUserId($user->id);

            if ($wallet->balance < $amount) {
                abort(422, 'Insufficient wallet balance');
            }

            $before = $wallet->balance;
            $after = $before - $amount;

            $this->walletRepo->updateBalance($wallet, $after);

            $transaction = $this->walletRepo->createTransaction([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference,
                'description' => $description,
            ]);

            // Send email notification
            Mail::to($user->email)->send(new WalletDebited($user, $amount, $after));

            return $transaction;
        });
    }

    public function transfer(
        User $from,
        User $to,
        float $amount,
        string $description
    ): void {
        DB::transaction(function () use ($from, $to, $amount, $description) {

            // Debit sender
            $this->debit(
                $from,
                $amount,
                "Transfer to {$to->email}: {$description}"
            );

            // Credit receiver
            $this->credit(
                $to,
                $amount,
                "Transfer from {$from->email}: {$description}"
            );
        });
    }
}
