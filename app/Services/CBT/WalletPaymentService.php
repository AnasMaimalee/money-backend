<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Models\User;
use App\Repositories\CBT\WalletPaymentRepository;
use App\Notifications\WalletDebitedNotification;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
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
        if ($exam->fee_paid) {
            throw new Exception('Exam fee already paid');
        }

        return DB::transaction(function () use ($userId, $exam, $amount) {
            $balance = (float) $this->repository->getUserBalance($userId);
            $amount  = (float) $amount;

            if ($balance < $amount) {
                throw new Exception('Insufficient wallet balance');
            }

            $groupReference = 'exam-fee-' . $exam->id;

            if ($this->repository->transactionExists($groupReference)) {
                throw new Exception('Exam fee already debited');
            }

            // 💸 1. DEBIT STUDENT WALLET (POSITIONAL args)
            $studentTransaction = $this->repository->debitWallet(
                $userId,
                $amount,
                'debit-' . $exam->id,
                $groupReference  // ✅ 4th param
            );


            // ✅ 2. MARK EXAM AS PAID
            $exam->update([
                'fee_paid' => true,
                'fee' => $amount
            ]);

            // 👑 3. DYNAMIC SUPERADMIN CREDIT
            $superadmin = User::whereHas('roles', function ($query) {
                $query->where('name', 'superadmin');
            })->first();

            $superadminTransaction = $this->repository->creditWallet(
                $superadmin->id,
                $amount,
                'credit-exam-' . $exam->id,
                $groupReference  // ✅ 4th param - NOW WORKS!
            );

            // 🔔 4. Notify student
            try {
                $user = User::findOrFail($userId);
                $user->notify(new WalletDebitedNotification(
                    amount: $amount,
                    purpose: 'CBT Exam Fee',
                    referenceId: $exam->id
                ));
            } catch (\Throwable $e) {
                logger()->warning('Student notification failed', ['exam_id' => $exam->id]);
            }

            return [
                'student_transaction' => $studentTransaction,
                'superadmin_transaction' => $superadminTransaction,
                'balance_after' => $studentTransaction->balance_after
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

    public function ongoingExams(Request $request)
    {
        $user = $request->user();

        $ongoingExams = Exam::where('user_id', $user->id)
            ->where('status', 'ongoing')
            ->with(['attempts.subject:id,name'])
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function ($exam) {
                $started = $exam->started_at;
                $duration = $exam->duration_minutes * 60;
                $elapsed = now()->diffInSeconds($started);
                $remaining = max(0, $duration - $elapsed);

                return [
                    'exam_id' => $exam->id,
                    'started_at' => $exam->started_at->toISOString(),
                    'duration_minutes' => $exam->duration_minutes,
                    'total_questions' => $exam->total_questions,
                    'subjects' => $exam->attempts->pluck('subject.name')->toArray(),
                    'time_remaining_seconds' => $remaining,
                    'expired' => $remaining <= 0,
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Ongoing exams retrieved',
            'data' => [
                'ongoing_exams' => $ongoingExams,
                'has_ongoing_exam' => $ongoingExams->isNotEmpty(),
                'count' => $ongoingExams->count(),
            ]
        ]);
    }
}
