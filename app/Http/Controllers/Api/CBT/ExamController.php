<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use App\Services\CBT\ExamService;
use App\Services\CBT\WalletPaymentService;
use Illuminate\Support\Str;

class ExamController extends Controller
{
    public function __construct(
        protected ExamService $service,
        protected WalletPaymentService $walletService
    ) {}

    // ---------------- START EXAM ----------------

    public function start(Request $request, WalletPaymentService $walletService)
    {
        $user = $request->user();

        $examFee = (float) config('cbt.exam_fee');

        if ($examFee <= 0) {
            return response()->json([
                'message' => 'Invalid exam fee configuration'
            ], 500);
        }

        $exam = Exam::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'total_questions' =>
                config('cbt.subjects_count') * config('cbt.questions_per_subject'),
            'duration_minutes' => config('cbt.duration_minutes'),
            'fee' => $examFee,
            'started_at' => now(),
        ]);

        // 🔥 THIS was where your error came from
        $walletService->debitExamFee(
            $user->id,
            $exam,
            $examFee
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Exam started successfully',
            'data' => $exam
        ]);
    }


    // ---------------- FETCH QUESTIONS ----------------
    public function show(Exam $exam)
    {
        $questions = $this->service->getExamQuestions($exam);

        return response()->json([
            'message' => 'Questions fetched',
            'questions' => $questions
        ]);
    }

    // ---------------- SAVE ANSWER ----------------
    public function answer(Request $request, Exam $exam)
    {
        $request->validate([
            'answer_id' => 'required|uuid',
            'selected_option' => 'required|in:A,B,C,D'
        ]);

        $answer = $this->service->answerQuestion(
            $exam->id,
            $request->answer_id,
            $request->selected_option
        );

        return response()->json([
            'message' => 'Answer saved',
            'answer' => $answer
        ]);
    }

    // ---------------- SUBMIT EXAM ----------------
    public function submit(Exam $exam)
    {
        $this->service->submitExam($exam);

        return response()->json([
            'message' => 'Exam submitted successfully'
        ]);
    }

    // ---------------- HANDLE NETWORK FAILURE REFUND ----------------
    public function refundIfUnsubmitted(Exam $exam)
    {
        try {
            $this->walletService->refundIfUnsubmitted($exam);
            return response()->json([
                'message' => 'Exam fee refunded due to network issue'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refund: ' . $e->getMessage()
            ], 400);
        }
    }
}
