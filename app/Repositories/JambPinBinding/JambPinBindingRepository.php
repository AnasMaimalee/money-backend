<?php

namespace App\Repositories\PinBinding;

use App\Http\Resources\JambPinBindingRequestResource;

class PinBindingRepository
{
    public function create(array $data)
    {
        return JambPinBindingRequestResource::create($data);
    }

    public function find(string $id)
    {
        return JambPinBindingRequestResource::findOrFail($id);
    }

    public function userRequests(string $userId)
    {
        return JambPinBindingRequestResource::where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function pending()
    {
        return JambPinBindingRequestResource::where('status', 'pending')->get();
    }

    public function allWithRelations()
    {
        return JambPinBindingRequestResource::with([
            'user',
            'service',
            'takenBy',
            'completedBy',
            'approvedBy',
            'rejectedBy',
        ])->latest()->get();
    }
}
