<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\ResultRepository;

class ResultService
{
    public function __construct(
        protected ResultRepository $resultRepository
    ) {}

    public function getResult(Exam $exam): array
    {
        // Ownership check
        if ($exam->user_id !== auth()->id()) {
            throw new \Exception('Unauthorized access to result');
        }

        if ($exam->status !== 'submitted') {
            throw new \Exception('Exam not yet submitted');
        }

        $attempts = $this->resultRepository
            ->getExamAttempts($exam->id);

        $answers = $this->resultRepository
            ->getExamAnswers($exam->id);

        $totalCorrect = $answers->where('is_correct', true)->count();
        $totalQuestions = $answers->count();
        $totalWrong = $totalQuestions - $totalCorrect;

        $percentage = $totalQuestions > 0
            ? round(($totalCorrect / $totalQuestions) * 100, 2)
            : 0;

        return [
            'exam' => [
                'exam_id' => $exam->id,
                'total_questions' => $totalQuestions,
                'correct' => $totalCorrect,
                'wrong' => $totalWrong,
                'percentage' => $percentage,
                'started_at' => $exam->started_at,
                'submitted_at' => $exam->submitted_at,
            ],

            'subjects' => $attempts->map(function ($attempt) {
                return [
                    'subject_id' => $attempt->subject->id,
                    'subject' => $attempt->subject->name,
                    'score' => $attempt->score,
                ];
            }),

            // 🔥 Frontend chart-ready
            'charts' => [
                'subject_scores' => $attempts->map(fn ($a) => [
                    'label' => $a->subject->name,
                    'value' => $a->score,
                ]),
                'overall' => [
                    'correct' => $totalCorrect,
                    'wrong' => $totalWrong,
                ],
            ],
        ];
    }

    public function summary(Exam $exam): array
    {
        if ($exam->status !== 'submitted') {
            throw new \Exception('Result not ready');
        }

        return [
            'exam_id' => $exam->id,
            'summary' => $this->repository->getSummary($exam),
        ];
    }

}
