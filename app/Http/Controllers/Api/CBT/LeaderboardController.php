<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Services\CBT\LeaderboardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LeaderboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected LeaderboardService $leaderboardService) {}

    /**
     * Full leaderboard - superadmin only
     */
    public function index()
    {
        $this->authorize('leaderboard');

        $leaderboard = $this->leaderboardService->getLeaderboard(100); // top 100

        return response()->json([
            'message' => 'Leaderboard fetched successfully',
            'data' => $leaderboard,
        ]);
    }

    /**
     * Self-rank for logged-in user
     */

    public function selfRank()
    {
        $this->authorize('leaderboardSelf');

        $rank = $this->leaderboardService->getUserRank(auth()->id());

        return response()->json([
            'message' => 'Your rank fetched successfully',
            'data' => $rank,
        ]);
    }

}
