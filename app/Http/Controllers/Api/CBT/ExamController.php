<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use App\Services\CBT\ExamService;
use App\Services\CBT\WalletPaymentService;

class ExamController extends Controller
{
    public function __construct(
        protected ExamService $examService,
        protected WalletPaymentService $walletService
    ) {}

    /* =====================================================
     | START EXAM
     ===================================================== */
    public function start(Request $request)
    {
        $request->validate([
            'subjects'   => 'required|array|size:' . config('cbt.subjects_count'),
            'subjects.*' => 'exists:subjects,id',
        ]);

        $user    = $request->user();
        $examFee = (float) config('cbt.exam_fee');

        if ($examFee <= 0) {
            return response()->json([
                'message' => 'Invalid exam fee configuration'
            ], 500);
        }

        // ✅ Start exam (creates exam + questions)
        $exam = $this->examService->startExam(
            $user->id,
            $request->subjects
        );

        // ✅ Debit wallet AFTER successful creation
        $this->walletService->debitExamFee(
            $user->id,
            $exam,
            $examFee
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Exam started successfully',
            'data'    => $exam
        ]);
    }

    /* =====================================================
     | FETCH QUESTIONS
     ===================================================== */
    public function show(Exam $exam)
    {
        $questions = $this->examService->getExamQuestions($exam);

        return response()->json([
            'message'   => 'Questions fetched',
            'questions' => $questions
        ]);
    }

    /* =====================================================
     | SAVE ANSWER (AUTOSAVE / NEXT)
     ===================================================== */
    public function submitAnswer(Request $request, Exam $exam, string $answerId)
    {
        $request->validate([
            'selected_option' => 'required|in:A,B,C,D'
        ]);

        $this->examService->answerQuestion(
            exam: $exam,
            answerId: $answerId,
            selectedOption: $request->selected_option
        );

        return response()->json([
            'message' => 'Answer saved successfully'
        ]);
    }

    /* =====================================================
     | SUBMIT EXAM
     ===================================================== */
    public function submit(Exam $exam)
    {
        $this->examService->submitExam($exam);

        return response()->json([
            'message' => 'Exam submitted successfully'
        ]);
    }

    /* =====================================================
     | REFUND IF NETWORK FAILURE
     ===================================================== */
    public function refundIfUnsubmitted(Exam $exam)
    {
        try {
            $this->walletService->refundExamFee($exam);

            return response()->json([
                'message' => 'Exam fee refunded due to network issue'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to refund: ' . $e->getMessage()
            ], 400);
        }
    }

    /* =====================================================
     | FETCH ONGOING EXAM
     ===================================================== */
    public function ongoingExams()
    {
        $exam = Exam::where('user_id', auth()->id())
            ->where('status', 'ongoing')
            ->latest('started_at')
            ->first();

        if (!$exam) {
            return response()->json([
                'message' => 'No ongoing exam found'
            ], 404);
        }

        return response()->json([
            'message' => 'Ongoing exam fetched',
            'data'    => $exam
        ]);
    }

    /* =====================================================
     | AUTO SUBMIT (TIME EXPIRED)
     ===================================================== */
    public function autoSubmitExam(Exam $exam)
    {
        if ($exam->user_id !== auth()->id()) {
            abort(403);
        }

        if ($exam->status !== 'ongoing') {
            return response()->json([
                'message' => 'Exam already submitted'
            ], 400);
        }

        // Safety check (time-based)
        if (now()->lt($exam->ends_at)) {
            return response()->json([
                'message' => 'Exam time not yet expired'
            ], 400);
        }

        $this->examService->submitExam($exam);

        return response()->json([
            'status'  => 'success',
            'message' => 'Exam auto-submitted (time expired)',
            'data'    => [
                'exam_id' => $exam->id,
                'total_score' => $exam->fresh()->total_score
            ]
        ]);
    }


    public function meta(Exam $exam)
    {
        $user = auth()->user();

        // Security check (important)
        if ($exam->user_id !== $user->id) {
            abort(403, 'Unauthorized access to exam');
        }

        $totalQuestions = $exam->answers()->count();
        $answered = $exam->answers()
            ->whereNotNull('selected_option')
            ->count();

        return response()->json([
            'exam_id' => $exam->id,
            'status' => $exam->status, // ongoing | submitted | expired
            'started_at' => $exam->started_at,
            'ends_at' => $exam->ends_at,
            'time_remaining' => max(
                0,
                now()->diffInSeconds($exam->ends_at, false)
            ),
            'total_questions' => $totalQuestions,
            'answered_questions' => $answered,
            'unanswered_questions' => $totalQuestions - $answered,
            'is_completed' => $exam->status !== 'ongoing',
        ]);
    }


}
