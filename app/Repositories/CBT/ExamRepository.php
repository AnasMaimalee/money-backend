<?php

namespace App\Repositories\CBT;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;

class ExamRepository
{
    public function findOngoingExam(int $userId): ?Exam
    {
        return Exam::where('user_id', $userId)
            ->where('status', 'ongoing')
            ->first();
    }

    public function createExam(array $data): Exam
    {
        return Exam::create($data);
    }

    public function createAttempt(int $examId, int $subjectId): void
    {
        ExamAttempt::create([
            'exam_id' => $examId,
            'subject_id' => $subjectId,
            'score' => 0,
        ]);
    }

    public function getRandomQuestions(int $subjectId, int $limit)
    {
        return Question::where('subject_id', $subjectId)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function createExamAnswer(int $examId, int $questionId): void
    {
        ExamAnswer::create([
            'exam_id' => $examId,
            'question_id' => $questionId,
            'selected_option' => null,
            'is_correct' => null,
        ]);
    }

    public function getExamQuestions(Exam $exam)
    {
        return ExamAnswer::with('question.subject')
            ->where('exam_id', $exam->id)
            ->get();
    }
    public function getOngoingExam(string $userId): ?Exam
    {
        return Exam::where('user_id', $userId)
            ->where('status', 'ongoing')
            ->latest()
            ->first();
    }

    public function getExamMeta(Exam $exam): array
    {
        return [
            'duration_minutes' => $exam->duration_minutes,
            'total_questions' => $exam->total_questions,
            'subjects' => $exam->attempts()
                ->with('subject:id,name')
                ->get()
                ->pluck('subject.name'),
        ];
    }

}
