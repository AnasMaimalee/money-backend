<?php

namespace App\Repositories\CBT;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WalletPaymentRepository
{
    /**
     * Get the current wallet balance for a user.
     */
    public function getUserBalance(string $userId): float
    {
        return User::where('id', $userId)->value('wallet_balance') ?? 0;
    }

    /**
     * Debit the wallet for a user.
     * Returns the WalletTransaction record.
     * Throws exception if balance insufficient.
     */
    public function debitWallet(string $userId, float $amount, string $examId): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $examId) {

            $user = User::findOrFail($userId);

            if ($user->wallet_balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            // Deduct balance
            $user->wallet_balance -= $amount;
            $user->save();

            // Record transaction
            $transaction = WalletTransaction::create([
                'id' => Str::uuid(),
                'user_id' => $userId,
                'amount' => $amount,
                'type' => 'debit',
                'description' => "CBT Exam Fee for Exam: $examId",
            ]);

            return $transaction;
        });
    }
}
