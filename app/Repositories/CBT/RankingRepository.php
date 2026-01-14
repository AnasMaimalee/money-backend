<?php

namespace App\Repositories\CBT;

use App\Models\Exam;

class RankingRepository
{
    public function getRanking()
    {
        return Exam::query()
            ->where('status', 'submitted')
            ->with('user:id,name')
            ->orderByDesc('total_score')
            ->orderBy('time_used_seconds')
            ->get()
            ->values() // important for correct indexing
            ->map(function ($exam, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $exam->user->id,
                    'name' => $exam->user->name,
                    'score' => $exam->total_score,
                    'time_used_seconds' => $exam->time_used_seconds,
                ];
            });
    }
}
