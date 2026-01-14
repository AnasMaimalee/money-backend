<?php

namespace App\Services\CBT;

use App\Repositories\CBT\ExamTimerRepository;
use App\Notifications\ExamSubmittedNotification;
use App\Models\Exam;
class ExamTimerService
{
    public function __construct(
        protected ExamTimerRepository $repo,
        protected SubmitExamService $submitService
    ) {}

    public function autoSubmitIfTimeUp(string $examId): ?array
    {
        $exam = $this->repo->getExam($examId);

        if (!$exam || $exam->status === 'submitted') {
            return null;
        }

        $timeElapsed = now()->diffInMinutes($exam->started_at);

        if ($timeElapsed < $exam->duration_minutes) {
            return null;
        }

        // ✅ System-level submission (bypass auth safely)
        $result = $this->submitService->systemSubmit($exam);

        // ✅ Notify user
        $exam->user->notify(
            new ExamSubmittedNotification($exam->id)
        );

        return $result;
    }

    public function systemSubmit(Exam $exam): array
    {
        if ($exam->status === 'submitted') {
            return [];
        }

        $answers = $this->submitExamRepository
            ->getExamAnswers($exam->id);

        $subjectScores = [];

        foreach ($answers as $answer) {
            $subjectId = $answer->question->subject_id;

            if (!isset($subjectScores[$subjectId])) {
                $subjectScores[$subjectId] = 0;
            }

            if ($answer->is_correct === true) {
                $subjectScores[$subjectId]++;
            }
        }

        foreach ($subjectScores as $subjectId => $score) {
            $this->submitExamRepository
                ->updateAttemptScore($exam->id, $subjectId, $score);
        }

        $this->submitExamRepository->submitExam($exam);

        return [
            'exam_id' => $exam->id,
            'total_questions' => $exam->total_questions,
            'scores' => $subjectScores,
            'auto_submitted' => true,
        ];
    }

    public function heartbeat(Exam $exam): array
    {
        if ($exam->status !== 'ongoing') {
            throw new \Exception('Exam is not active');
        }

        $this->repo->updateLastHeartbeat($exam);

        $expiresAt = $exam->started_at->addMinutes($exam->duration_minutes);

        return [
            'server_time' => now(),
            'expires_at' => $expiresAt,
            'remaining_seconds' => now()->diffInSeconds($expiresAt, false),
        ];
    }

}
