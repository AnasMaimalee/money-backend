<?php

namespace App\Repositories\JambUploadStatus;

use App\Models\JambUploadStatusRequest;

class JambUploadStatusRepository
{
    public function create(array $data)
    {
        return JambUploadStatusRequest::create($data);
    }

    public function find(string $id)
    {
        return JambUploadStatusRequest::findOrFail($id);
    }

    public function userRequests(string $userId)
    {
        return JambUploadStatusRequest::where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function pending()
    {
        return JambUploadStatusRequest::where('status', 'pending')->get();
    }

    public function allWithRelations()
    {
        return JambUploadStatusRequest::with([
            'user',
            'service',
            'takenBy',
            'completedBy',
            'approvedBy',
            'rejectedBy',
        ])->latest()->get();
    }
}
