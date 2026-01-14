<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Services\CBT\RankingService;

class RankingController extends Controller
{
    public function __construct(
        protected RankingService $service
    ) {}

    public function leaderboard()
    {
        return response()->json([
            'message' => 'Leaderboard fetched successfully',
            'data' => $this->service->leaderboard(),
        ]);
    }
}
