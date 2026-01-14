<?php

namespace App\Services\CBT;

use App\Repositories\CBT\RankingRepository;

class RankingService
{
    public function __construct(
        protected RankingRepository $repository
    ) {}

    public function leaderboard(): array
    {
        return [
            'generated_at' => now()->toDateTimeString(),
            'rankings' => $this->repository->getRanking(),
        ];
    }
}
