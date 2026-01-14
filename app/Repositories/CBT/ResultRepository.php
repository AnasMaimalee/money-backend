<?php

namespace App\Repositories\CBT;

use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Exam;

class ResultRepository
{
    /**
     * Detailed breakdown per subject
     */
    public function subjectBreakdown(string $examId)
    {
        $answers = ExamAnswer::with('question.subject')
            ->where('exam_id', $examId)
            ->get();

        $grouped = [];

        foreach ($answers as $answer) {
            $subject = $answer->question->subject;
            $subjectId = $subject->id;

            $grouped[$subjectId]['subject'] = $subject->name;
            $grouped[$subjectId]['total'] = ($grouped[$subjectId]['total'] ?? 0) + 1;

            if ($answer->is_correct) {
                $grouped[$subjectId]['correct'] =
                    ($grouped[$subjectId]['correct'] ?? 0) + 1;
            }
        }

        return collect($grouped)->map(function ($data) use ($examId) {
            $correct = $data['correct'] ?? 0;
            $total = $data['total'];
            $wrong = $total - $correct;

            $score = ExamAttempt::where('exam_id', $examId)
                ->whereHas('subject', fn ($q) =>
                $q->where('name', $data['subject'])
                )
                ->value('score');

            return [
                'subject' => $data['subject'],
                'total_questions' => $total,
                'correct' => $correct,
                'wrong' => $wrong,
                'score' => $score,
            ];
        })->values();
    }

    /**
     * Fetch all attempts for an exam
     */
    public function getExamAttempts(string $examId)
    {
        return ExamAttempt::with('subject')
            ->where('exam_id', $examId)
            ->get();
    }

    /**
     * Fetch all answers for an exam
     */
    public function getExamAnswers(string $examId)
    {
        return ExamAnswer::with('question.subject')
            ->where('exam_id', $examId)
            ->get();
    }

    /**
     * Lightweight exam summary for dashboard/history
     */
    public function getSummary(Exam $exam): array
    {
        return [
            'exam_id' => $exam->id,
            'total_score' => $exam->total_score,
            'time_used_seconds' => $exam->time_used_seconds,
            'status' => $exam->status,
            'submitted_at' => $exam->submitted_at,
        ];
    }
}
