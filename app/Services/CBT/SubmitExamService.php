<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\ResultRepository;
use Illuminate\Support\Facades\DB;

class SubmitExamService
{
    public function __construct(
        protected ResultRepository $resultRepository,
        protected ExamSessionService $examSessionService
    ) {}

    /**
     * Submit an exam (manual submission)
     */
    public function submit(Exam $exam): array
    {
        // Ownership check
        if ($exam->user_id !== auth()->id()) {
            throw new \Exception('Unauthorized access to submit exam');
        }

        // Check if already submitted
        if ($exam->status === 'submitted') {
            throw new \Exception('Exam already submitted');
        }

        // Wrap in transaction
        DB::transaction(function () use ($exam) {
            // Update exam status
            $exam->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Calculate total score (optional)
            $attempts = $this->resultRepository->getExamAttempts($exam->id);
            $answers = $this->resultRepository->getExamAnswers($exam->id);

            $totalScore = $answers->where('is_correct', true)->count();
            $exam->update(['total_score' => $totalScore]);

            // You can also calculate time used if needed
            if ($exam->started_at) {
                $exam->update([
                    'time_used_seconds' => now()->diffInSeconds($exam->started_at),
                ]);
            }
        });

        // Return full result after submission
        return $this->getResult($exam);
    }

    /**
     * Full result breakdown (dashboard / PDF / charts)
     */
    public function getResult(Exam $exam): array
    {
        // Ownership check
        if ($exam->user_id !== auth()->id()) {
            throw new \Exception('Unauthorized access to result');
        }

        if ($exam->status !== 'submitted') {
            throw new \Exception('Exam not yet submitted');
        }

        $attempts = $this->resultRepository->getExamAttempts($exam->id);
        $answers = $this->resultRepository->getExamAnswers($exam->id);

        $totalCorrect = $answers->where('is_correct', true)->count();
        $totalQuestions = $answers->count();
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
            'subjects' => $attempts->map(function ($attempt) use ($answers) {
                $subjectAnswers = $answers->filter(
                    fn ($a) => $a->question->subject_id === $attempt->subject_id
                );

                $correct = $subjectAnswers->where('is_correct', true)->count();
                $total = $subjectAnswers->count();

                return [
                    'subject_id' => $attempt->subject->id,
                    'subject' => $attempt->subject->name,
                    'total_questions' => $total,
                    'correct' => $correct,
                    'wrong' => $total - $correct,
                    'score' => $attempt->score,
                ];
            })->values(),
            'charts' => [
                'subject_scores' => $attempts->map(fn ($a) => [
                    'label' => $a->subject->name,
                    'value' => $a->score,
                ])->values(),
                'overall' => [
                    'correct' => $totalCorrect,
                    'wrong' => $totalWrong,
                ],
            ],
        ];
    }

    /**
     * Lightweight summary (lists / history)
     */
    public function summary(Exam $exam): array
    {
        if ($exam->status !== 'submitted') {
            throw new \Exception('Result not ready');
        }

        return [
            'exam_id' => $exam->id,
            'total_score' => $exam->total_score,
            'time_used_seconds' => $exam->time_used_seconds,
            'submitted_at' => $exam->submitted_at,
        ];
    }
}
