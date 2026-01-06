<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected string $baseUrl = 'https://api.paystack.co';

    public function initiateTransfer(
        int $amount,
        string $recipientCode,
        string $reason
    ): array {
        $response = Http::withToken(config('services.paystack.secret'))
            ->post("{$this->baseUrl}/transfer", [
                'source'    => 'balance',
                'amount'    => $amount * 100, // kobo
                'recipient' => $recipientCode,
                'reason'    => $reason,
            ]);

        if (! $response->successful()) {
            abort(500, 'Paystack payout failed');
        }

        return $response->json('data');
    }
}
