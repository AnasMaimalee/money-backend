<?php

namespace App\Repositories\CBT;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAnswer;

class SubmitExamRepository
{
    public function getExamAnswers(string $examId)
    {
        return ExamAnswer::with('question')
            ->where('exam_id', $examId)
            ->get();
    }

    public function updateAttemptScore(
        string $examId,
        string $subjectId,
        int $score
    ): void {
        ExamAttempt::where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->update(['score' => $score]);
    }

    public function submitExam(Exam $exam): void
    {
        $exam->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}
