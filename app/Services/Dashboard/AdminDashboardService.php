<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminDashboardService
{
    public function summary(User $admin): array
    {
        if (!$admin->hasRole('administrator')) {
            abort(403, 'Unauthorized');
        }

        $tables = [
            'jamb_result_requests',
            'jamb_admission_letter_requests',
            'jamb_upload_status_requests',
            'jamb_admission_status_requests',
            'jamb_admission_result_notification_requests'
        ];

        $totalJobs = 0;
        $completedJobs = 0;
        $rejectedJobs = 0;
        $allJobsByService = [];
        $recentJobs = [];

        foreach ($tables as $table) {
            $serviceName = $this->getServiceName($table);

            // ✅ Confirmed column from your migration: 'completed_by'
            $totalForTable = DB::table($table)
                ->where('completed_by', $admin->id)
                ->count();

            if ($totalForTable > 0) {
                $totalJobs += $totalForTable;

                // ✅ Fixed status matching your migration
                $completedForTable = DB::table($table)
                    ->where('completed_by', $admin->id)
                    ->whereIn('status', ['completed', 'approved'])
                    ->count();
                $completedJobs += $completedForTable;

                $rejectedForTable = DB::table($table)
                    ->where('completed_by', $admin->id)
                    ->where('status', 'rejected')
                    ->count();
                $rejectedJobs += $rejectedForTable;

                $allJobsByService[] = [
                    'service' => $serviceName,
                    'jobs' => $totalForTable,
                    'completed' => $completedForTable,
                    'rejected' => $rejectedForTable
                ];

                // Recent jobs
                $recent = DB::table($table)
                    ->where('completed_by', $admin->id)
                    ->orderBy('updated_at', 'desc')
                    ->limit(2)
                    ->get(['id', 'user_id', 'status', 'created_at', 'updated_at']);

                $recentJobs = array_merge($recentJobs, $recent->toArray());
            }
        }

        // ✅ FIXED: SQLite-compatible avg processing time
        $avgProcessingTime = $this->getAverageProcessingTime($admin);

        return [
            'stats' => [
                'processed_jobs' => $totalJobs,
                'completed_jobs' => $completedJobs,
                'rejected_jobs' => $rejectedJobs,
                'pending_jobs' => $totalJobs - $completedJobs - $rejectedJobs,
                'performance' => $totalJobs > 0
                    ? round(($completedJobs / $totalJobs) * 100, 2) . '%'
                    : '0%',
                'earnings' => (float) DB::table('wallet_transactions')
                    ->where('user_id', $admin->id)
                    ->where('type', 'credit')
                    ->sum('amount'),
                'avg_processing_time' => $avgProcessingTime
            ],

            'jobs_by_service' => $allJobsByService,

            'recent_jobs' => array_map(function($job) {
                return [
                    'id' => $job->id,
                    'user_id' => $job->user_id,
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                    'updated_at' => $job->updated_at,
                    'ago' => $this->timeAgo($job->updated_at)
                ];
            }, array_slice($recentJobs, 0, 10)),

            'fraud_flags' => [
                'high_rejection_rate' => $totalJobs >= 10 && ($rejectedJobs / max($totalJobs, 1)) > 0.5,
                'too_many_pending' => ($totalJobs - $completedJobs - $rejectedJobs) > 50,
                'low_performance' => $totalJobs > 0 && ($completedJobs / $totalJobs) < 0.7
            ]
        ];
    }

    private function getServiceName(string $table): string
    {
        $map = [
            'jamb_result_requests' => 'JAMB Original Result',
            'jamb_admission_letter_requests' => 'Admission Letter',
            'jamb_upload_status_requests' => "O'Level Upload Status",
            'jamb_admission_status_requests' => 'Admission Status Check',
            'jamb_admission_result_notification_requests' => 'Result Notification'
        ];

        return $map[$table] ?? ucwords(str_replace('_requests', '', str_replace('jamb_', '', $table)));
    }

    /**
     * ✅ FIXED: SQLite-compatible average processing time
     */
    private function getAverageProcessingTime(User $admin): string
    {
        $tables = [
            'jamb_result_requests',
            'jamb_admission_letter_requests',
            'jamb_upload_status_requests',
            'jamb_admission_status_requests',
            'jamb_admission_result_notification_requests'
        ];

        $totalMinutes = 0;
        $count = 0;

        foreach ($tables as $table) {
            $jobs = DB::table($table)
                ->where('completed_by', $admin->id)
                ->whereNotNull('created_at')
                ->whereNotNull('updated_at')
                ->where('status', 'completed')
                ->get(['created_at', 'updated_at']);

            foreach ($jobs as $job) {
                $start = Carbon::parse($job->created_at);
                $end = Carbon::parse($job->updated_at);
                $minutes = $start->diffInMinutes($end, false);

                if ($minutes > 0) {
                    $totalMinutes += $minutes;
                    $count++;
                }
            }
        }

        return $count > 0 ? round($totalMinutes / $count) . ' mins' : 'N/A';
    }

    private function timeAgo(string $datetime): string
    {
        $now = now();
        $diffInHours = $now->diffInHours(Carbon::parse($datetime));

        if ($diffInHours < 1) return 'Just now';
        if ($diffInHours < 24) return $diffInHours . 'h ago';
        if ($diffInHours < 168) return round($diffInHours / 24) . 'd ago';
        return $now->copy()->subHours($diffInHours)->format('M j');
    }
}
