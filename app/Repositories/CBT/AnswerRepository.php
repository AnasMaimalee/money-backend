<?php

namespace App\Repositories\CBT;

use App\Models\ExamAnswer;
use Illuminate\Support\Str;

class AnswerRepository
{
    public function saveOrUpdate(array $data): ExamAnswer
    {
        return ExamAnswer::updateOrCreate(
            [
                'exam_id' => $data['exam_id'],
                'question_id' => $data['question_id'],
            ],
            [
                'id' => $data['id'] ?? Str::uuid(),
                'selected_option' => $data['selected_option'],
                'is_correct' => $data['is_correct'],
            ]
        );
    }

    public function getUserAnswer(string $examId, string $questionId): ?ExamAnswer
    {
        return ExamAnswer::where('exam_id', $examId)
            ->where('question_id', $questionId)
            ->first();
    }
}
