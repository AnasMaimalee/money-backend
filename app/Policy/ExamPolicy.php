<?php

namespace App\Policy;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function view(User $user, Exam $exam): bool
    {
        return $exam->user_id === $user->id;
    }
}
