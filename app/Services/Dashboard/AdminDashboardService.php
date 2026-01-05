<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\ServiceRequest;
use App\Models\WalletTransaction;

class AdminDashboardService
{
    public function summary(User $admin): array
    {
        if (! $admin->hasRole('administrator')) {
            abort(403, 'Unauthorized');
        }

        // All jobs touched by this admin
        $jobs = ServiceRequest::where('completed_by', $admin->id);

        $total     = (clone $jobs)->count();
        $completed = (clone $jobs)->whereIn('status', ['completed','approved'])->count();
        $rejected  = (clone $jobs)->where('status','rejected')->count();

        return [
            'stats' => [
                'processed_jobs' => $total,
                'completed_jobs' => $completed,
                'rejected_jobs'  => $rejected,

                'performance' => $total
                    ? round(($completed / $total) * 100, 2) . '%'
                    : '0%',

                'earnings' => WalletTransaction::where('user_id', $admin->id)
                    ->where('type', 'credit')
                    ->sum('amount'),
            ],

            'jobs_by_service' => ServiceRequest::with('service')
                ->where('completed_by', $admin->id)
                ->selectRaw('service_id, COUNT(*) as total')
                ->groupBy('service_id')
                ->get()
                ->map(fn ($j) => [
                    'service' => optional($j->service)->name,
                    'jobs'    => $j->total,
                ]),

            'fraud_flags' => [
                'high_rejection_rate' =>
                    $total >= 10 && ($rejected / max($total,1)) > 0.5,

                'too_fast_processing' => false, // extend later
            ],
        ];
    }
}
