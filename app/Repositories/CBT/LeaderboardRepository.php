<?php

namespace App\Repositories\CBT;

use App\Models\Exam;

class LeaderboardRepository
{
    /**
     * Top N students, ranked by total_score, then least time_used_seconds
     */
    public function getTopExams(int $limit = 50)
    {
        return Exam::where('status', 'submitted')
            ->with('user:id,name')
            ->orderByDesc('total_score')
            ->orderBy('time_used_seconds')
            ->limit($limit)
            ->get()
            ->map(function ($exam, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $exam->user->id,
                    'name' => $exam->user->name,
                    'total_score' => $exam->total_score,
                    'time_used_seconds' => $exam->time_used_seconds,
                ];
            });
    }
}
