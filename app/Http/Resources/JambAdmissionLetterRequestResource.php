<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JambAdmissionLetterRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'is_paid' => $this->is_paid,
            'profile_code' => $this->profile_code,
            'registration_number' => $this->registration_number,

            'result_available' => ! empty($this->result_file),

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
