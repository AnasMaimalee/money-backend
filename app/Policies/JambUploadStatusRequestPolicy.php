<?php

namespace App\Policies;

use App\Models\User;
use App\Models\JambUploadStatusRequest;
use Illuminate\Auth\Access\Response;
class JambUploadStatusRequestPolicy
{
    /**
     * View Result file
     */
    public function view(User $user, JambUploadStatusRequest $job): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($user->hasRole('administrator')) {
            return $job->completed_by === $user->id;
        }

        return $job->user_id === $user->id;
    }

    
    /**
     * Download result file
     */
    public function download(User $user, JambUploadStatusRequest $job): Response
    {
        // ✅ Superadmin: Full access
        if ($user->hasRole('superadmin')) {
            return Response::allow();
        }

        // ✅ Owner: Own request
        if ($job->user_id === $user->id) {
            return Response::allow();
        }

        // ✅ Administrator: Processed by them
        if ($user->hasRole('administrator') && $job->completed_by === $user->id) {
            return Response::allow();
        }

        // ✅ DENY with clear message
        return Response::deny('You are not authorized to download this file. Contact admin.');
    }


    /**
     * Allow Administrator to take Jon
     */
    public function take(User $user, JambUploadStatusRequest $job): bool
    {
        return $user->hasRole('administrator')
            && $job->status === 'pending';
    }

    public function approve(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function reject(User $user): bool
    {
        return $user->hasRole('superadmin');
    }
}
