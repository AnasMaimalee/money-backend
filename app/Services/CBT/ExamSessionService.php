<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\ExamSessionRepository;

class ExamSessionService
{
    public function __construct(
        protected ExamSessionRepository $repository
    ) {}

    /**
     * Start exam session
     */
    public function startSession(Exam $exam): void
    {
        $this->repository->create([
            'exam_id' => $exam->id,
            'user_id' => $exam->user_id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($exam->duration_minutes),
            'status' => 'ongoing',
        ]);
    }

    /**
     * Get exam session
     */
    public function getSession(string $examId)
    {
        return $this->repository->findByExam($examId);
    }

    /**
     * End session (manual or auto submit)
     */
    public function endSession(Exam $exam): void
    {
        $session = $this->repository->findByExam($exam->id);

        if (!$session || $session->status !== 'ongoing') {
            return;
        }

        $this->repository->update($session->id, [
            'status' => 'ended',
            'ended_at' => now(),
        ]);
    }

    /**
     * Mark session expired (timer / heartbeat)
     */
    public function markExpired(Exam $exam): void
    {
        $session = $this->repository->findByExam($exam->id);

        if (!$session || $session->status !== 'ongoing') {
            return;
        }

        $this->repository->update($session->id, [
            'status' => 'expired',
            'ended_at' => now(),
        ]);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(Exam $exam): bool
    {
        $session = $this->repository->findByExam($exam->id);

        if (!$session) {
            return true;
        }

        return now()->greaterThan($session->expires_at);
    }
}
