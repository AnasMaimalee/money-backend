<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\ResultRepository;

class ResultService
{
    public function __construct(
        protected ResultRepository $resultRepository,
        protected ExamSessionService $examSessionService
    ) {}

    /**
     * Full result breakdown
     */
    public function getResult(Exam $exam): array
    {
        // Ownership check
        if ($exam->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to result');
        }

        if ($exam->status !== 'submitted') {
            abort(400, 'Exam not yet submitted');
        }

        // Use subjectBreakdown
        $subjectBreakdown = $this->resultRepository->subjectBreakdown($exam->id);

        $totalCorrect = collect($subjectBreakdown)->sum('correct');
        $totalQuestions = collect($subjectBreakdown)->sum('total_questions');
        $totalWrong = $totalQuestions - $totalCorrect;
        $percentage = $totalQuestions > 0
            ? round(($totalCorrect / $totalQuestions) * 100, 2)
            : 0;

        return [
            'exam' => [
                'exam_id' => $exam->id,
                'total_score' => $exam->total_score,
                'total_questions' => $totalQuestions,
                'correct' => $totalCorrect,
                'wrong' => $totalWrong,
                'percentage' => $percentage,
                'time_used_seconds' => $exam->time_used_seconds,
                'submitted_at' => $exam->submitted_at,
            ],

            // Subjects breakdown
            'subjects' => $subjectBreakdown,

            // Chart-ready data
            'charts' => [
                'subject_scores' => collect($subjectBreakdown)
                    ->map(fn($s) => [
                        'label' => $s['subject'],
                        'value' => $s['score'],
                    ])->values(),
                'overall' => [
                    'correct' => $totalCorrect,
                    'wrong' => $totalWrong,
                ],
            ],
        ];
    }

    /**
     * Lightweight summary for dashboard/history
     */
    public function summary(Exam $exam): array
    {
        if ($exam->status !== 'submitted') {
            abort(400, 'Result not ready');
        }

        return $this->resultRepository->getSummary($exam);
    }
}
