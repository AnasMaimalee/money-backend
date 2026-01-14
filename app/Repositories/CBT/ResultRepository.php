<?php

namespace App\Repositories\CBT;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAnswer;

class ResultRepository
{
    public function getExamAttempts(string $examId)
    {
        return ExamAttempt::with('subject')
            ->where('exam_id', $examId)
            ->get();
    }

    public function getExamAnswers(string $examId)
    {
        return ExamAnswer::with('question')
            ->where('exam_id', $examId)
            ->get();
    }

    public function getExamById(string $examId): ?Exam
    {
        return Exam::find($examId);
    }

    public function getSummary(Exam $exam): array
    {
        return $exam->attempts()
            ->with('subject:id,name')
            ->get()
            ->map(fn ($attempt) => [
                'subject' => $attempt->subject->name,
                'score' => $attempt->score,
            ])
            ->toArray();
    }

}
