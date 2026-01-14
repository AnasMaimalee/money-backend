<?php

namespace App\Repositories\CBT;

use App\Models\Wallet;
use App\Models\WalletTransaction;

class WalletPaymentRepository
{
    /**
     * Get user's wallet balance
     */
    public function getUserBalance(string $userId): float
    {
        return (float) Wallet::where('user_id', $userId)
            ->value('balance');
    }

    /**
     * Check if transaction already exists (idempotency)
     */
    public function transactionExists(string $groupReference): bool
    {
        return WalletTransaction::where('group_reference', $groupReference)->exists();
    }

    /**
     * Debit wallet safely (row locked)
     */
    public function debitWallet(
        string $userId,
        float $amount,
        string $reference,
        string $groupReference
    ): WalletTransaction {
        $wallet = Wallet::where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();

        $before = (float) $wallet->balance;
        $after  = $before - $amount;

        $wallet->update(['balance' => $after]);

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference' => $reference,
            'group_reference' => $groupReference,
            'description' => 'CBT Exam Fee',
        ]);
    }

    /**
     * Credit wallet (refund)
     */
    public function creditWallet(
        string $userId,
        float $amount,
        string $reference
    ): WalletTransaction {
        $wallet = Wallet::where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();

        $before = (float) $wallet->balance;
        $after  = $before + $amount;

        $wallet->update(['balance' => $after]);

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference' => $reference,
            'description' => 'CBT Exam Fee Refund',
        ]);
    }
}
