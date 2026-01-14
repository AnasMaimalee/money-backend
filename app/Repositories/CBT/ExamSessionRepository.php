<?php

namespace App\Repositories\CBT;

use App\Models\ExamSession;

class ExamSessionRepository
{
    public function create(array $data): ExamSession
    {
        return ExamSession::create($data);
    }

    public function findByExam(string $examId): ?ExamSession
    {
        return ExamSession::where('exam_id', $examId)->first();
    }

    public function markSubmitted(ExamSession $session): void
    {
        $session->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);
    }

    public function markExpired(ExamSession $session): void
    {
        $session->update([
            'status' => 'expired'
        ]);
    }
}
