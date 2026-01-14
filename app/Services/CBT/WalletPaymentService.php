<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Models\User;
use App\Repositories\CBT\WalletPaymentRepository;
use App\Notifications\WalletDebitedNotification;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;

class WalletPaymentService
{
    public function __construct(protected WalletPaymentRepository $repository) {}

    // Check wallet balance
    public function checkBalance(string $userId): float
    {
        return (float) $this->repository->getUserBalance($userId);
    }


    // Debit wallet for exam fee

    public function debitExamFee(string $userId, Exam $exam, float $amount)
    {
        // 🔒 Prevent double payment
        if ($exam->fee_paid) {
            throw new Exception('Exam fee already paid');
        }

        return DB::transaction(function () use ($userId, $exam, $amount) {

            // ✅ Always cast to float
            $balance = (float) $this->repository->getUserBalance($userId);
            $amount  = (float) $amount;

            if ($balance < $amount) {
                throw new Exception('Insufficient wallet balance');
            }

            // 🧾 Idempotency reference (one debit per exam)
            $groupReference = 'exam-fee-' . $exam->id;

            // 🔁 Prevent duplicate transaction
            if ($this->repository->transactionExists($groupReference)) {
                throw new Exception('Exam fee already debited');
            }

            // 💸 Debit wallet
            $transaction = $this->repository->debitWallet(
                userId: $userId,
                amount: $amount,
                reference: $exam->id,
                groupReference: $groupReference
            );

            // ✅ Mark exam as paid
            $exam->update([
                'fee_paid' => true,
                'fee' => $amount
            ]);

            // 🔔 Notify user (non-blocking)
            try {
                $user = User::findOrFail($userId);
                $user->notify(new WalletDebitedNotification(
                    amount: $amount,
                    purpose: 'CBT Exam Fee',
                    referenceId: $exam->id
                ));
            } catch (\Throwable $e) {
                // Log but do NOT rollback payment
                logger()->warning('Wallet debit notification failed', [
                    'exam_id' => $exam->id,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'transaction' => $transaction,
                'balance_after' => $transaction->balance_after
            ];
        });
    }


    // Refund exam fee if exam not submitted
    public function refundExamFee(Exam $exam): void
    {
        if ($exam->fee_paid && !$exam->fee_refunded && !$exam->submitted_at) {
            DB::transaction(function () use ($exam) {
                $transaction = $this->repository->creditWallet($exam->user_id, $exam->fee, $exam->id);

                $exam->fee_refunded = true;
                $exam->save();

                $user = User::findOrFail($exam->user_id);
                $user->notify(new WalletDebitedNotification(
                    amount: $exam->fee,
                    purpose: 'CBT Exam Fee Refund',
                    referenceId: $exam->id
                ));
            });
        }
    }
}
