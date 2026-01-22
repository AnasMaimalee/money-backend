<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JambPinBindingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'email' => $this->email,
            'profile_code' => $this->profile_code,
            'registration_number' => $this->registration_number,

            'result_available' => ! empty($this->result_file),

            'user' => [
                'name' => $this->user?->name,
            ],

            'service' => $this->service?->name,

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

