<?php

namespace App\Repositories\CBT\SuperAdmin;

use App\Models\Question;

class QuestionBankRepository
{
    public function getQuestions(array $filters = [], int $perPage = 20)
    {
        $query = Question::with('subject')->orderBy('created_at', 'desc');

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('question', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate($perPage);
    }

    public function findQuestion(string $questionId)
    {
        return Question::with('subject')->findOrFail($questionId);
    }
}
