<?php

namespace App\Services\Payout;
use App\Enums\PayoutStatus;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminPayoutService
{
    public function __construct(
        protected PaystackPayoutService $paystack
    ) {}

    public function request(User $admin, float $amount): PayoutRequest
    {
        if (! $admin->hasRole('administrator')) {
            abort(403, 'Unauthorized');
        }

        if (! $admin->bankAccount) {
            abort(422, 'Please set bank details first');
        }

        if ($amount > $admin->wallet->balance) {
            abort(422, 'Insufficient wallet balance');
        }

        if ($admin->payoutRequests()->where('status', 'pending')->exists()) {
            abort(422, 'You already have a pending payout request');
        }

        return PayoutRequest::create([
            'admin_id'         => $admin->id,
            'amount'           => $amount,
            'status'           => 'pending',
            'balance_snapshot' => $admin->wallet->balance,
        ]);
    }

    public function approveAndPay(
        PayoutRequest $payout,
        User $superAdmin
    ): array {
        if (! $superAdmin->hasRole('super-admin')) {
            abort(403);
        }

        if ($payout->status !== 'pending') {
            abort(422, 'Invalid payout state');
        }

        return DB::transaction(function () use ($payout, $superAdmin) {

            $admin = $payout->admin;

            // 🔐 Paystack transfer
            $paystackResponse = $this->paystack->transfer([
                'amount'         => $payout->amount,
                'recipient_code' => $admin->bankAccount->recipient_code,
                'reason'         => 'Admin payout withdrawal',
            ]);

            // 🔐 Debit wallet AFTER successful transfer
            app('wallet')->debit(
                $admin,
                $payout->amount,
                'Admin payout withdrawal'
            );

            $payout->update([
                'status'      => 'paid',
                'approved_by' => $superAdmin->id,
                'approved_at' => now(),
                'reference'   => $paystackResponse['reference'],
            ]);

            return [
                'payout_id' => $payout->id,
                'reference' => $paystackResponse['reference'],
                'amount'    => $payout->amount,
            ];
        });
    }
}
