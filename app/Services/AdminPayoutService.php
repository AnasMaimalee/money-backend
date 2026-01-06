<?php
namespace App\Services;

use App\Models\User;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPayoutService
{
    public function __construct(
        protected WalletRepository $walletRepo,
        protected PaystackService $paystack
    ) {}

    public function payout(User $admin, float $amount)
    {
        $wallet = $this->walletRepo->getByUserId($admin->id);

        if ($wallet->balance < $amount) {
            abort(422, 'Insufficient payout balance');
        }

        if (! $admin->bank_recipient_code) {
            abort(422, 'Admin bank account not configured');
        }

        return DB::transaction(function () use ($admin, $wallet, $amount) {

            // 1️⃣ Initiate Paystack transfer
            $paystack = $this->paystack->initiateTransfer(
                amount: $amount,
                recipientCode: $admin->bank_recipient_code,
                reason: 'Admin earnings payout'
            );

            // 2️⃣ Debit wallet
            $before = $wallet->balance;
            $after  = $before - $amount;

            $this->walletRepo->updateBalance($wallet, $after);

            // 3️⃣ Record payout transaction
            $transaction = $this->walletRepo->createPayoutTransaction([
                'wallet_id'        => $wallet->id,
                'user_id'          => $admin->id,
                'type'             => 'payout',
                'amount'           => $amount,
                'balance_before'   => $before,
                'balance_after'    => $after,
                'reference'        => Str::uuid(),
                'meta' => [
                    'paystack_reference' => $paystack['reference'],
                    'transfer_code'      => $paystack['transfer_code'],
                ],
            ]);

            return $transaction;
        });
    }
}
