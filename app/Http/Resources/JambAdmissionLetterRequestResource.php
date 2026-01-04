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

            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'profile_code' => $this->profile_code,
            'registration_number' => $this->registration_number,

            'customer_price' => $this->customer_price,
            'admin_payout' => $this->admin_payout,
            'platform_profit' => $this->platform_profit,

            'result_file' => $this->result_file
                ? asset('storage/' . $this->result_file)
                : null,

            'user' => $this->whenLoaded('user'),
            'service' => $this->whenLoaded('service'),

            'taken_by' => $this->whenLoaded('takenBy'),
            'completed_by' => $this->whenLoaded('completedBy'),
            'approved_by' => $this->whenLoaded('approvedBy'),
            'rejected_by' => $this->whenLoaded('rejectedBy'),

            'created_at' => $this->created_at,
        ];
    }
}
