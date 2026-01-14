<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Exam;

class ExamPolicy
{
    /**
     * Determine if the user can view the exam/result
     */
    public function view(User $user, Exam $exam): bool
    {
        // Superadmin can view everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Users can view only their own exams
        return $user->id === $exam->user_id;
    }

    /**
     * Determine if the user can download the PDF result
     */
    public function downloadPdf(User $user, Exam $exam): bool
    {
        // Same rules as view
        return $this->view($user, $exam);
    }

    /**
     * Determine if the user can update the exam
     */
    public function update(User $user, Exam $exam): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine if the user can delete the exam
     */
    public function delete(User $user, Exam $exam): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine if the user can view leaderboard
     */
    public function leaderboard(User $user): bool
    {
        // Only superadmin can see full leaderboard
        return $user->hasRole('superadmin');
    }

    /**
     * Determine if the user can see their own rank on leaderboard
     */
    public function leaderboardSelf(User $user): bool
    {
        // Any authenticated user can see their own rank
        return $user->hasRole('user') || $user->hasRole('superadmin');
    }
}
