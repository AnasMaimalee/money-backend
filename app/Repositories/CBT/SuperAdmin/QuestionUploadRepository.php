<?php

namespace App\Repositories\CBT\SuperAdmin;

use App\Models\Question;
use Illuminate\Support\Str;

class QuestionUploadRepository
{
    public function create(array $data): void
    {
        Question::create([
            'id' => $data['id'],
            'subject_id' => $data['subject_id'],
            'question' => $data['question'],
            'option_a' => $data['option_a'],
            'option_b' => $data['option_b'],
            'option_c' => $data['option_c'],
            'option_d' => $data['option_d'],
            'correct_option' => $data['correct_option'],
            'image' => $data['image'] ?? null,
            'year' => $data['year'] ?? null,
        ]);
    }
}
