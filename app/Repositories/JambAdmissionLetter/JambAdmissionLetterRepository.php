<?php

namespace App\Repositories\JambAdmissionLetter;

use App\Models\JambAdmissionLetterRequest;

class JambAdmissionLetterRepository
{
    public function create(array $data)
    {
        return JambAdmissionLetterRequest::create($data);
    }

    public function find(string $id)
    {
        return JambAdmissionLetterRequest::findOrFail($id);
    }

    public function userRequests(string $userId)
    {
        return JambAdmissionLetterRequest::where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function pending()
    {
        return JambAdmissionLetterRequest::where('status', 'pending')->get();
    }

    public function allWithRelations()
    {
        return JambAdmissionLetterRequest::with([
            'user',
            'service',
            'takenBy',
            'completedBy',
            'approvedBy',
            'rejectedBy',
        ])->latest()->get();
    }
}
