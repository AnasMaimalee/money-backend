<?php

namespace App\Services\Paystack;

use App\Models\PayoutRequest;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

class PaystackWebhookService
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function handleTransferSuccess(array $payload): void
    {
        $reference = $payload['data']['reference'] ?? null;

        if (! $reference) {
            return;
        }

        $payout = PayoutRequest::where('paystack_reference', $reference)->first();

        if (! $payout || $payout->status === 'paid') {
            return; // idempotent
        }

        DB::transaction(function () use ($payout) {

            // ✅ Debit admin wallet AFTER real money leaves Paystack
            $this->walletService->debit(
                $payout->admin,
                $payout->amount,
                'Admin payout'
            );

            $payout->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);
        });
    }
}
