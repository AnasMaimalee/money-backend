<?php

namespace App\Services\CBT;

use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Get top N users by total score
     */
    public function getLeaderboard(int $limit = 100): array
    {
        // Only include submitted exams
        $leaderboard = Exam::select('user_id', DB::raw('SUM(total_score) as total_score'))
            ->where('status', 'submitted')
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $item->user_id,
                    'total_score' => $item->total_score,
                ];
            });

        return $leaderboard->toArray();
    }

    /**
     * Get rank of a specific user
     */
    public function getUserRank(string $userId): array
    {
        // Get total score of each user for submitted exams
        $allScores = Exam::select('user_id', DB::raw('SUM(total_score) as total_score'))
            ->where('status', 'submitted')
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->get();

        // Find the rank
        $rank = 0;
        foreach ($allScores as $index => $item) {
            if ($item->user_id === $userId) {
                $rank = $index + 1;
                $totalScore = $item->total_score;
                break;
            }
        }

        if ($rank === 0) {
            return [
                'rank' => null,
                'total_score' => 0,
                'message' => 'User has no submitted exams yet',
            ];
        }

        return [
            'rank' => $rank,
            'total_score' => $totalScore,
        ];
    }
}
