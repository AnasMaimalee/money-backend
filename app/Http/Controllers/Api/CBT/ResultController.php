<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CBT\ResultService;
use App\Services\CBT\ResultPdfService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ResultController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ResultService $resultService,
        protected ResultPdfService $pdfService
    ) {}

    /**
     * Show full result with breakdown
     */
    public function show(Exam $exam)
    {

        $result = $this->resultService->getResult($exam);

        return response()->json([
            'message' => 'Result fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Summary for dashboard / history
     */
    public function summary(Exam $exam)
    {
        $this->authorize('view', $exam);

        $summary = $this->resultService->summary($exam);

        return response()->json([
            'message' => 'Summary fetched successfully',
            'data' => $summary,
        ]);
    }

    /**
     * Generate PDF result slip
     */
    public function downloadResult(Exam $exam, ResultPdfService $pdfService)
    {
        // 🔐 Ensure exam belongs to authenticated user


        // ✅ Build subject breakdown EXACTLY as PDF expects
        $subjects = $exam->answers()
            ->with('question.subject')
            ->get()
            ->groupBy(fn ($ans) => $ans->question->subject->name)
            ->map(function ($items, $subject) {
                $total = $items->count();
                $correct = $items->where('is_correct', true)->count();

                return [
                    'subject' => $subject,
                    'total_questions' => $total,
                    'correct' => $correct,
                    'wrong' => $total - $correct,
                    'score' => $correct, // adjust later if subject has weight
                ];
            })
            ->values()
            ->toArray();

        $breakdown = [
            'subjects' => $subjects,
        ];

        // ✅ Generate & download PDF
        return $pdfService->generate($exam, $breakdown);
    }

    public function showResult(string $examId)
    {
        $exam = Exam::with(['answers.question.subject'])->where('id', $examId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $subjects = $exam->answers
            ->groupBy(fn($a) => $a->question->subject->name)
            ->map(fn($answers, $subjectName) => [
                'subject' => $subjectName,
                'total' => $answers->count(),
                'correct' => $answers->where('is_correct', true)->count(),
                'wrong' => $answers->where('is_correct', false)->count(),
                'questions' => $answers->map(fn($a) => [
                    'question' => $a->question->question,
                    'options' => [
                        'A' => $a->question->option_a,
                        'B' => $a->question->option_b,
                        'C' => $a->question->option_c,
                        'D' => $a->question->option_d,
                    ],
                    'selected' => $a->selected_option,
                    'correct' => $a->question->correct_option,
                    'is_correct' => $a->is_correct,
                ]),
            ])->values();

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'total_questions' => $exam->answers->count(),
                'correct' => $exam->answers->where('is_correct', true)->count(),
                'percentage' => round($exam->answers->where('is_correct', true)->count() / max(1, $exam->answers->count()) * 100),
                'submitted_at' => $exam->submitted_at,
            ],
            'subjects' => $subjects,
        ]);
    }

}
