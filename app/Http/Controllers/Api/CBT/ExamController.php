<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\CbtSetting;
use App\Models\Exam;
use App\Repositories\CBT\CbtSettingRepository;
use App\Repositories\CBT\ExamRepository;
use App\Services\CBT\ExamResultService;
use Illuminate\Http\Request;
use App\Services\CBT\ExamService;
use App\Services\CBT\WalletPaymentService;
use Illuminate\Support\Facades\DB;
use App\Notifications\WalletDebitedNotification;
class ExamController extends Controller
{
    public function __construct(
        protected ExamService $examService,
        protected ExamRepository $examRepository,
        protected WalletPaymentService $walletService,
        protected ExamResultService $examResultService,
        protected CbtSettingRepository $cbtSettingRepository,

    ) {}

    public function examFee()
    {
        $cbtSetting = CbtSetting::query()->first();

        if (!$cbtSetting) {
            return response()->json([
                'message' => 'CBT settings not configured yet'
            ], 400);
        }

        $examFee = (float) $cbtSetting->exam_fee;

    }

   


    /* =====================================================
     | START EXAM - FIXED
     ===================================================== */
    public function start(Request $request)
    {

        $request->validate([
            'subjects'   => 'required|array|size:' . config('cbt.subjects_count'),
            'subjects.*' => 'exists:subjects,id',
        ]);

        $user = $request->user();


        DB::beginTransaction();
        try {
            // ✅ 1. Create exam FIRST
            $exam = $this->examService->startExam($user->id, $request->subjects);

            // ✅ 2. ONLY call debitExamFee if $exam exists
            if (!$exam || !$exam->exists) {
                DB::rollBack();
                return response()->json(['error' => 'Failed to create exam'], 500);
            }

            // ✅ 3. SAFE wallet debit - exam guaranteed to exist
            $this->walletService->debitExamFee(
                $user->id,
                $exam,  // ✅ Exam exists here
                $examFee
            );

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Exam started successfully',
                'data'    => [
                    'exam_id'   => $exam->id,
                    'exam_code' => $exam->exam_code ?? $exam->id,
                    'subjects'  => $request->subjects
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            // ✅ FIXED: Only refund if $exam exists
            if (isset($exam) && $exam && $exam->exists) {
                try {
                    $this->walletService->refundExamFee($exam);
                } catch (\Throwable $refundError) {
                    \Log::error('Refund failed after exam start error', [
                        'exam_id' => $exam->id ?? 'unknown',
                        'error' => $refundError->getMessage()
                    ]);
                }
            }

            return response()->json([
                'message' => 'Failed to start exam: ' . $e->getMessage()
            ], 500);
        }
    }

    /* =====================================================
     | FETCH EXAM + QUESTIONS - FIXED (No Model Binding)
     ===================================================== */
    public function show(Request $request, $examId)
    {
        $exam = $this->findUserExam($examId);
        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }
        // ✅ MANUAL LOOKUP - No Route Model Binding crash
        $exam = Exam::where('id', $examId)
            ->orWhere('exam_code', $examId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $questions = $this->examService->getExamQuestions($exam);

        return response()->json([
            'status'     => 'success',
            'exam_id'    => $exam->id,
            'questions'  => $questions,
            'subjects'   => $exam->subjects ?? []
        ]);
    }

    /* =====================================================
     | EXAM META - FIXED
     ===================================================== */
    public function meta(Request $request, $examId)
    {
        $exam = Exam::where('id', $examId)
            ->orWhere('exam_code', $examId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $totalQuestions = $exam->answers()->count();
        $answered = $exam->answers()->whereNotNull('selected_option')->count();

        return response()->json([
            'exam_id'            => $exam->id,
            'status'             => $exam->status,
            'time_remaining'     => max(0, now()->diffInSeconds($exam->ends_at, false)),
            'total_time'         => now()->diffInSeconds($exam->started_at),
            'total_questions'    => $totalQuestions,
            'answered_questions' => $answered,
        ]);
    }

    /* =====================================================
     | SAVE ANSWER - FIXED
     ===================================================== */
    public function submitAnswer(Request $request, $examId, string $answerId)
    {
        $request->validate([
            'selected_option' => 'required|in:A,B,C,D'
        ]);

        $exam = Exam::where('id', $examId)
            ->orWhere('exam_code', $examId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

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
     | SUBMIT EXAM - FIXED
     ===================================================== */
    public function submit(Request $request, $examId)
    {
        $exam = Exam::where('id', $examId)
            ->orWhere('exam_code', $examId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $this->examService->submitExam($exam);
        $this->examResultService->generate($exam);
        return response()->json([
            'message' => 'Exam submitted successfully',
            'exam_id' => $exam->id
        ]);
    }
    private function findUserExam($examId): ?Exam
    {
        return Exam::where('id', $examId)
            ->orWhere('exam_code', $examId)
            ->where('user_id', auth()->id())
            ->first();
    }
    /* =====================================================
     | AUTO SUBMIT - FIXED
     ===================================================== */
    public function autoSubmitExam(Request $request, $examId)
    {
        $exam = Exam::where('id', $examId)
            ->orWhere('exam_code', $examId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($exam->status !== 'ongoing') {
            return response()->json(['message' => 'Exam already submitted'], 400);
        }

        $this->examService->submitExam($exam);

        return response()->json([
            'status'  => 'success',
            'message' => 'Exam auto-submitted',
            'exam_id' => $exam->id
        ]);
    }

    /* =====================================================
     | REFUND - FIXED
     ===================================================== */
    public function refundExamFee(?Exam $exam = null, float $amount = 0): void
    {
        // ✅ SAFE: Handle null exam
        if (!$exam || !$exam->exists || !$exam->fee_paid || $exam->fee_refunded || $exam->submitted_at) {
            return; // No refund needed
        }

        DB::transaction(function () use ($exam, $amount) {
            // Credit user wallet
            $this->repository->creditWallet(
                $exam->user_id,
                $exam->fee ?: $amount,
                'refund-' . $exam->id,
                'exam-fee-' . $exam->id
            );

            // Mark as refunded
            $exam->update([
                'fee_refunded' => true
            ]);

            // Notify user
            $user = User::find($exam->user_id);
            if ($user) {
                $user->notify(new WalletDebitedNotification(
                    amount: $exam->fee ?: $amount,
                    purpose: 'CBT Exam Fee Refund',
                    referenceId: $exam->id
                ));
            }
        });
    }


    /* =====================================================
     | ONGOING EXAM - UNCHANGED (Working)
     ===================================================== */
    public function ongoingExams()
    {
        $exam = Exam::where('user_id', auth()->id())
            ->where('status', 'ongoing')
            ->latest('started_at')
            ->first();

        if (!$exam) {
            return response()->json(['message' => 'No ongoing exam found'], 404);
        }

        return response()->json([
            'message' => 'Ongoing exam fetched',
            'data'    => [
                'exam_id' => $exam->id,
                'exam_code' => $exam->exam_code ?? $exam->id
            ]
        ]);
    }

    /* =====================================================
     | RESUME + TIME - FIXED
     ===================================================== */
    public function resume(Request $request)
    {
        $user = $request->user();
        $exam = $this->examRepository->findOngoingExam($user->id);

        if (!$exam) {
            return response()->json(['message' => 'No ongoing exam'], 404);
        }

        return response()->json([
            'message' => 'Exam resumed',
            'data' => [
                'exam_id' => $exam->id,
                'exam_code' => $exam->exam_code ?? $exam->id
            ]
        ]);
    }

    public function time(Request $request)
    {
        $exam = $this->examService
            ->getOngoingExamForUser($request->user()->id);

        abort_if(!$exam, 404, 'No ongoing exam');

        return response()->json([
            'remaining_seconds' => max(
                0,
                now()->diffInSeconds($exam->ends_at, false)
            )
        ]);
    }
}
