<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\ExamRepository;
use Illuminate\Support\Facades\DB;
use App\Notifications\ExamStartedNotification;

class ExamService
{
    public function __construct(
        protected ExamRepository $examRepository,
        protected ExamSessionService $examSessionService
    ) {}

    /**
     * Start a new CBT exam
     */
    public function startExam(string $userId, array $subjectIds): Exam
    {
        $existing = $this->examRepository->findOngoingExam($userId);

        if ($existing) {
            throw new \Exception('You already have an ongoing exam');
        }

        $questionsPerSubject = 15;
        $durationMinutes = 120;

        return DB::transaction(function () use (
            $userId,
            $subjectIds,
            $questionsPerSubject,
            $durationMinutes
        ) {

            // 1️⃣ Create Exam
            $exam = $this->examRepository->createExam([
                'user_id' => $userId,
                'total_questions' => count($subjectIds) * $questionsPerSubject,
                'duration_minutes' => $durationMinutes,
                'status' => 'ongoing',
                'started_at' => now(),
            ]);

            // 2️⃣ Start Exam Session
            $this->examSessionService->startSession($exam);

            // 3️⃣ Create Attempts + Questions
            foreach ($subjectIds as $subjectId) {
                $this->examRepository->createAttempt($exam->id, $subjectId);

                $questions = $this->examRepository
                    ->getRandomQuestions($subjectId, $questionsPerSubject);

                foreach ($questions as $question) {
                    $this->examRepository
                        ->createExamAnswer($exam->id, $question->id);
                }
            }

            // 4️⃣ Notify User
            $exam->user->notify(
                new ExamStartedNotification($exam->id)
            );

            return $exam;
        });
    }

    /**
     * Get questions for an exam
     */
    public function getExamQuestions(Exam $exam): array
    {
        // Ownership check
        if ($exam->user_id !== auth()->id()) {
            throw new \Exception('Unauthorized exam access');
        }

        $session = $this->examSessionService->getSession($exam->id);

        if (!$session || $session->status !== 'ongoing') {
            throw new \Exception('Exam session is not active');
        }

        if (now()->greaterThan($session->expires_at)) {
            throw new \Exception('Exam time has expired');
        }

        return [
            'exam' => [
                'id' => $exam->id,
                'started_at' => $session->started_at,
                'expires_at' => $session->expires_at,
                'duration_minutes' => $exam->duration_minutes,
            ],
            'questions' => $this->examRepository
                ->getExamQuestions($exam)
                ->map(function ($answer) {
                    return [
                        'answer_id' => $answer->id,
                        'question_id' => $answer->question->id,
                        'subject' => $answer->question->subject->name,
                        'question' => $answer->question->question,
                        'options' => [
                            'A' => $answer->question->option_a,
                            'B' => $answer->question->option_b,
                            'C' => $answer->question->option_c,
                            'D' => $answer->question->option_d,
                        ],
                        'selected_option' => $answer->selected_option,
                    ];
                })
                ->values(),
        ];
    }

    /**
     * Get ongoing exam for dashboard/resume
     */
    public function getOngoingExam(string $userId): ?array
    {
        $exam = $this->examRepository->getOngoingExam($userId);

        if (!$exam) return null;

        $session = $this->examSessionService->getSession($exam->id);

        return [
            'exam_id' => $exam->id,
            'started_at' => $session?->started_at,
            'expires_at' => $session?->expires_at,
            'status' => $session?->status,
        ];
    }

    /**
     * Exam metadata (before start/payment)
     */
    public function getExamMeta(Exam $exam): array
    {
        return array_merge(
            $this->examRepository->getExamMeta($exam),
            [
                'fee' => config('cbt.exam_fee'),
                'instructions' => [
                    'Do not refresh the page',
                    'Exam auto-submits when time elapses',
                    'Ensure stable internet connection',
                ],
            ]
        );
    }
}
