<?php

namespace App\Http\Controllers\Api\Webhooks;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Paystack\PaystackWebhookService;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaystackWebhookService $service)
    {
        $payload = $request->all();

        match ($payload['event'] ?? null) {
            'transfer.success' => $service->handleTransferSuccess($payload),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }
}
