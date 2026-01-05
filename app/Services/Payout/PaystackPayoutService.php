<?php

namespace App\Services\Payout;

use Illuminate\Support\Facades\Http;

class PaystackPayoutService
{
    public function transfer(array $data): array
    {
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transfer', [
                'source'   => 'balance',
                'amount'   => (int) ($data['amount'] * 100), // kobo
                'recipient'=> $data['recipient_code'],
                'reason'   => $data['reason'],
            ]);

        if (! $response->successful()) {
            abort(500, 'Paystack payout failed');
        }

        return $response->json('data');
    }
}
