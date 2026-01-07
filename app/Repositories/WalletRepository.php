<?php

namespace App\Repositories;

use App\Models\Wallet;
use App\Models\WalletTransaction;

class WalletRepository
{
    public function getByUserId(string $userId): Wallet
    {
        return Wallet::where('user_id', $userId)->firstOrFail();
    }

    public function createTransaction(array $data): WalletTransaction
    {
        return WalletTransaction::create($data);
    }

    public function updateBalance(Wallet $wallet, float $amount): Wallet
    {
        $wallet->update(['balance' => $amount]);
        return $wallet;
    }

    public function createPayoutTransaction(array $data): WalletTransaction
    {
        return WalletTransaction::create($data);
    }
}
