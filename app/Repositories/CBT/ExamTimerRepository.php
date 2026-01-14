<?php

namespace App\Repositories\CBT;

use App\Models\Exam;

class ExamTimerRepository
{
    public function getExam(string $examId): ?Exam
    {
        return Exam::find($examId);
    }

    public function markAsSubmitted(Exam $exam): void
    {
        $exam->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);
    }

    public function updateLastHeartbeat(Exam $exam): void
    {
        $exam->update([
            'last_heartbeat_at' => now()
        ]);
    }

}
