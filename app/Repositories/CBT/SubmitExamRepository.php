<?php

namespace App\Repositories\CBT;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAnswer;

class SubmitExamRepository
{
    /**
     * Get all answers for an exam
     */
    public function getExamAnswers(string $examId)
    {
        return ExamAnswer::with('question')
            ->where('exam_id', $examId)
            ->get();
    }

    /**
     * Update score for a subject attempt
     */
    public function updateAttemptScore(
        string $examId,
        string $subjectId,
        float $score
    ): void {
        ExamAttempt::where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->update([
                'score' => $score,
            ]);
    }

    /**
     * Finalize exam submission
     */
    public function submitExam(Exam $exam, array $data): void
    {
        $exam->update($data);
    }
}
