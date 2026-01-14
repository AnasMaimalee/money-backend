<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\ExamSessionRepository;

class ExamSessionService
{
    public function __construct(
        protected ExamSessionRepository $repository
    ) {}

    public function startSession(Exam $exam): void
    {
        $this->repository->create([
            'exam_id' => $exam->id,
            'user_id' => $exam->user_id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($exam->duration_minutes),
            'status' => 'ongoing'
        ]);
    }

    public function getSession(string $examId)
    {
        return $this->repository->findByExam($examId);
    }
}
