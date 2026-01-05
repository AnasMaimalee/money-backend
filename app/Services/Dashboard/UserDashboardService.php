<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\JambResultRequest;
use App\Models\JambAdmissionLetterRequest;
use App\Models\JambUploadStatusRequest;
use App\Models\JambAdmissionStatusRequest;
use App\Models\JambAdmissionResultNotificationRequest;

class UserDashboardService
{
    public function summary(User $user): array
    {
        // 🔹 Query all services
        $services = [
            'Jamb Original Result' => JambResultRequest::where('user_id', $user->id),
            'Jamb Admission Letter' => JambAdmissionLetterRequest::where('user_id', $user->id),
            'JAMB Upload Status' => JambUploadStatusRequest::where('user_id', $user->id),
            'Checking Admission Status' => JambAdmissionStatusRequest::where('user_id', $user->id),
            'JAMB Results Notifications' => JambAdmissionResultNotificationRequest::where('user_id', $user->id),
        ];

        $totalJobs = 0;
        $approved = 0;
        $rejected = 0;
        $pending = 0;
        $servicesUsage = [];

        foreach ($services as $name => $query) {
            $count = $query->count();

            $totalJobs += $count;
            $approved += (clone $query)->where('status', 'approved')->count();
            $rejected += (clone $query)->where('status', 'rejected')->count();
            $pending += (clone $query)->whereIn('status', ['pending', 'processing', 'completed'])->count();

            $servicesUsage[] = [
                'service' => $name,
                'total_jobs' => $count,
            ];
        }

        return [
            /*
            |--------------------------------------------------------------------------
            | STATS
            |--------------------------------------------------------------------------
            */
            'stats' => [
                'total_jobs' => $totalJobs,
                'approved' => $approved,
                'rejected' => $rejected,
                'pending' => $pending,

                // ✅ Wallet is the only money truth
                'total_spent' => WalletTransaction::where('user_id', $user->id)
                    ->where('type', 'debit')
                    ->sum('amount'),
            ],

            /*
            |--------------------------------------------------------------------------
            | SERVICES USAGE
            |--------------------------------------------------------------------------
            */
            'services_usage' => $servicesUsage,

            /*
            |--------------------------------------------------------------------------
            | RECENT JOBS (MERGED)
            |--------------------------------------------------------------------------
            */
            'recent_jobs' => collect([
                JambResultRequest::where('user_id', $user->id)->latest()->first(),
                JambAdmissionLetterRequest::where('user_id', $user->id)->latest()->first(),
                JambUploadStatusRequest::where('user_id', $user->id)->latest()->first(),
                JambAdmissionStatusRequest::where('user_id', $user->id)->latest()->first(),
                JambAdmissionResultNotificationRequest::where('user_id', $user->id)->latest()->first(),
            ])
                ->filter()
                ->sortByDesc('created_at')
                ->take(5)
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'service' => class_basename($job),
                    'status' => $job->status,
                    'submitted_at' => $job->created_at->toDateTimeString(),
                ])
                ->values(),
        ];
    }
}
