<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CBT\WalletPaymentService;
use Illuminate\Http\Request;

class WalletPaymentController extends Controller
{
    public function __construct(
        protected WalletPaymentService $service
    ) {}

    // Step 1: Show balance + exam fee
    public function check(Request $request, Exam $exam)
    {
        $balance = $this->service->checkBalance($request->user()->id);
        $fee = $exam->fee; // assume `fee` column in exams table

        return response()->json([
            'balance' => $balance,
            'exam_fee' => $fee,
            'can_pay' => $balance >= $fee
        ]);
    }

    // Step 2: Debit wallet and start exam
    public function payAndStart(Request $request, Exam $exam)
    {
        $fee = $exam->fee;

        try {
            $transaction = $this->service->payExamFee(
                $request->user()->id,
                $exam,
                $fee
            );

            return response()->json([
                'message' => "Exam fee of $fee has been debited from wallet.",
                'transaction_id' => $transaction->id,
                'exam_unlocked' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'exam_unlocked' => false
            ], 400);
        }
    }
}
