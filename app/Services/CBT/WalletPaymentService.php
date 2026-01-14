<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Models\User;
use App\Repositories\CBT\WalletPaymentRepository;
use App\Notifications\WalletDebitedNotification;
use Illuminate\Support\Facades\DB;

class WalletPaymentService
{
    public function __construct(
        protected WalletPaymentRepository $repository
    ) {}

    /**
     * Check user wallet balance
     */
    public function checkBalance(string $userId): float
    {
        return $this->repository->getUserBalance($userId);
    }

    /**
     * Debit exam fee from user wallet.
     *
     * @throws \Exception if insufficient balance
     */
    public function payExamFee(string $userId, Exam $exam, float $amount): void
    {
        DB::transaction(function () use ($userId, $exam, $amount) {

            // ✅ Get current balance
            $balance = $this->repository->getUserBalance($userId);

            if ($balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            // ✅ Debit the wallet
            $transaction = $this->repository->debitWallet(
                $userId,
                $amount,
                $exam->id
            );

            // ✅ Notify user of successful debit
            $user = User::findOrFail($userId);
            $user->notify(
                new WalletDebitedNotification(
                    amount: $amount,
                    purpose: 'CBT Exam Fee',
                    referenceId: $exam->id
                )
            );
        });
    }
}
