<?php

namespace App\Repositories\JambAdmissionStatus;

use App\Models\JambAdmissionStatusRequest;

class JambAdmissionStatusRepository
{
    public function create(array $data)
    {
        return JambAdmissionStatusRequest::create($data);
    }

    public function find(string $id)
    {
        return JambAdmissionStatusRequest::findOrFail($id);
    }

    public function userRequests(string $userId)
    {
        return JambAdmissionStatusRequest::where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function pending()
    {
        return JambAdmissionStatusRequest::where('status', 'pending')->get();
    }

    public function allWithRelations()
    {
        return JambAdmissionStatusRequest::with([
            'user',
            'service',
            'takenBy',
            'completedBy',
            'approvedBy',
            'rejectedBy',
        ])->latest()->get();
    }
}
