<?php

namespace App\Repositories\JambAdmissionLetter;

use App\Models\JambAdmissionLetterRequest;
use App\Models\Wallet;
class JambAdmissionLetterRepository
{
    public function create(array $data)
    {
        return JambAdmissionLetterRequest::create($data);
    }
    
    public function findOrFail(string $id): JambAdmissionLetterRequest
    {
        return JambAdmissionLetterRequest::findOrFail($id);
    }


    public function getAll()
    {
        return JambAdmissionLetterRequest::with([
            'user:id,name,email'
        ])
            ->latest()
            ->get();
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

    public function getByUserIdForUpdate(string $userId)
    {
        return Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }
    public function findForUpdate(string $id)
    {
        return JambAdmissionLetterRequest::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();
    }


}
