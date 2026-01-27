<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;
class SuperAdminDashboardService
{
    private array $requestTables = [
        'jamb_result_requests',
        'jamb_admission_letter_requests',
        'jamb_upload_status_requests',
        'jamb_admission_status_requests',
        'jamb_admission_result_notification_requests',
        'jamb_pin_binding_requests'
    ];

    public function summary(): array
    {
        // 1. USER STATS
        $totalUsers = User::count();
        $totalAdmins = User::role('administrator')->count();
        $totalWalletsBalance = DB::table('wallets')->sum('balance');

        // 2. JOBS STATS - ALL 5 TABLES
        $stats = $this->getJobsStats();

        // 3. SERVICE BREAKDOWN
        $jobsByService = $this->getJobsByService();

        // 4. ADMIN LEADERBOARD
        $adminLeaderboard = $this->getAdminLeaderboard();

        // 5. FINANCIAL STATS
        $financials = $this->getFinancialStats();

        return [
            'overview' => [
                // Users
                'total_users' => $totalUsers,
                'total_admins' => $totalAdmins,
                'total_wallets_balance' => (float) $totalWalletsBalance,

                // Jobs (ALL tables combined)
                'total_jobs' => $stats['total'],
                'pending_jobs' => $stats['pending'],
                'processing_jobs' => $stats['processing'],
                'completed_jobs' => $stats['completed'],
                'approved_jobs' => $stats['approved'],
                'rejected_jobs' => $stats['rejected'],
                'approval_rate' => $stats['total'] > 0 ? round(($stats['completed'] + $stats['approved']) / $stats['total'] * 100, 1) : 0,

                // Financials
                ...$financials
            ],

            'jobs_by_service' => $jobsByService,
            'admin_leaderboard' => $adminLeaderboard,

            'system_health' => [
                'admins_with_zero_jobs' => $totalAdmins - count(array_filter($adminLeaderboard, fn($admin) => $admin['jobs'] > 0)),
                'avg_completion_time' => $this->getAvgCompletionTime(),
            ]
        ];
    }

    private function getJobsStats(): array
    {
        $stats = [
            'total' => 0, 'pending' => 0, 'processing' => 0,
            'completed' => 0, 'approved' => 0, 'rejected' => 0
        ];

        foreach ($this->requestTables as $table) {
            // Check if table exists first
            if (!Schema::hasTable($table)) continue;

            $tableStats = DB::table($table)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                ")
                ->first();

            $stats['total'] += $tableStats->total;
            $stats['pending'] += $tableStats->pending;
            $stats['processing'] += $tableStats->processing;
            $stats['completed'] += $tableStats->completed;
            $stats['approved'] += $tableStats->approved;
            $stats['rejected'] += $tableStats->rejected;
        }

        return $stats;
    }

    private function getJobsByService(): array
    {
        $services = [];

        foreach ($this->requestTables as $table) {
            if (!Schema::hasTable($table)) continue;

            $total = DB::table($table)->count();
            if ($total === 0) continue;

            $serviceName = $this->formatServiceName($table);

            $services[] = [
                'service' => $serviceName,
                'total' => $total,
                'pending' => DB::table($table)->whereIn('status', ['pending'])->count(),
                'processing' => DB::table($table)->where('status', 'processing')->count(),
                'completed' => DB::table($table)->where('status', 'completed')->count(),
                'approved' => DB::table($table)->where('status', 'approved')->count(),
                'rejected' => DB::table($table)->where('status', 'rejected')->count(),
            ];
        }

        return $services;
    }

    private function getAdminLeaderboard(): array
    {
        $admins = User::role('administrator')->get(['id', 'name', 'email']);
        $leaderboard = [];

        foreach ($admins as $admin) {
            $jobs = 0;
            foreach ($this->requestTables as $table) {
                if (Schema::hasTable($table)) {
                    $jobs += DB::table($table)->where('completed_by', $admin->id)->count();
                }
            }

            $leaderboard[] = [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'jobs' => $jobs,
                'earnings' => (float) DB::table('wallet_transactions')
                    ->where('user_id', $admin->id)
                    ->where('type', 'credit')
                    ->sum('amount'),
                'wallet_balance' => (float) DB::table('wallets')
                        ->where('user_id', $admin->id)
                        ->value('balance') ?? 0,
            ];
        }

        return collect($leaderboard)
            ->sortByDesc('jobs')
            ->values()
            ->toArray();
    }

    private function getFinancialStats(): array
    {
        return [
            'total_revenue' => (float) DB::table('wallet_transactions')
                ->where('type', 'debit')
                ->sum('amount'),
            'admin_payouts' => (float) DB::table('wallet_transactions')
                ->where('type', 'credit')
                ->whereIn('user_id', User::role('administrator')->pluck('id'))
                ->sum('amount'),
            'platform_profit' => (float) array_sum(array_column($this->getJobsByService(), 'platform_profit') ?? []),
            'total_wallets_balance' => (float) DB::table('wallets')->sum('balance'),
        ];
    }

    private function formatServiceName(string $table): string
    {
        $map = [
            'jamb_result_requests' => 'JAMB Result',
            'jamb_admission_letter_requests' => 'Admission Letter',
            'jamb_upload_status_requests' => 'O\'Level Upload',
            'jamb_admission_status_requests' => 'Admission Status',
            'jamb_admission_result_notification_requests' => 'Result Notification',
            'jamb_pin_binding_requests' => 'PIN Binding'
        ];

        return $map[$table] ?? ucwords(str_replace('_requests', '', str_replace('jamb_', '', $table)));
    }

    private function getAvgCompletionTime(): string
    {
        $totalMinutes = 0;
        $count = 0;

        foreach ($this->requestTables as $table) {
            if (!Schema::hasTable($table)) continue;

            $result = DB::table($table)
                ->where('status', 'completed')
                ->whereNotNull('created_at')
                ->whereNotNull('updated_at')
                ->selectRaw('AVG((strftime("%s", updated_at) - strftime("%s", created_at)) / 60.0) as avg_minutes')
                ->first();

            if ($result->avg_minutes > 0) {
                $totalMinutes += $result->avg_minutes;
                $count++;
            }
        }

        return $count > 0 ? round($totalMinutes / $count) . ' mins' : 'N/A';
    }
}
