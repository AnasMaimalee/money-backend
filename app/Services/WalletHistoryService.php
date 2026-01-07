<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\WalletRepository;

class WalletHistoryService
{
    public function __construct(
        protected WalletRepository $walletRepository
    ) {}

    /**
     * Fetch wallet transactions with filters
     */
    public function getHistory(array $filters = [], ?User $user = null)
    {
        $query = $this->walletRepository->transactionsQuery();

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if (!empty($filters['month'])) {
            $query->whereMonth('created_at', $filters['month']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('created_at', $filters['year']);
        }

        if (!empty($filters['from']) && !empty($filters['to'])) {
            $query->whereBetween('created_at', [
                $filters['from'],
                $filters['to']
            ]);
        }

        return $query->latest()->get();
    }
}
