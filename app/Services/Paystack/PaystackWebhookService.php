<?php

namespace App\Services\Paystack;

use App\Models\PayoutRequest;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaystackWebhookService
{
    public function __construct(protected WalletService $walletService)
    {
    }

    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? null;

        Log::emergency('STEP 1: Webhook service started', [
            'event' => $event,
            'reference' => $payload['data']['reference'] ?? 'missing'
        ]);

        match ($event) {
            'charge.success' => $this->handleChargeSuccess($payload),
            'transfer.success' => $this->handleTransferSuccess($payload),
            default => Log::warning("Unhandled Paystack event: {$event}", $payload),
        };

        Log::emergency('STEP 4: Webhook service finished processing');
    }

    public function handleChargeSuccess(array $payload): void
    {
        Log::emergency('STEP 2: Inside charge.success handler');

        $data = $payload['data'] ?? [];
        $reference = $data['reference'] ?? null;
        $amount = ($data['amount'] ?? 0) / 100;
        $email = $data['customer']['email'] ?? null;
        $status = $data['status'] ?? null;

        Log::emergency('STEP 2.1: Parsed basic data', [
            'reference' => $reference,
            'amount' => $amount,
            'email' => $email,
            'status' => $status
        ]);

        // -------------------- BASIC VALIDATION --------------------
        if (!$reference || !$email || $amount <= 0 || $status !== 'success') {
            Log::warning('Invalid charge.success payload - skipped', $payload);
            return;
        }

        // -------------------- FIND USER --------------------
        Log::emergency('STEP 2.2: Looking for user by email', ['email' => $email]);

        $user = User::where('email', $email)->first();

        if (!$user) {
            Log::emergency('STEP 2.3: USER NOT FOUND - trying case-insensitive');
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        }

        if (!$user) {
            Log::emergency('STEP 2.4: STILL NO USER FOUND - stopping here', ['email' => $email]);
            return;
        }

        Log::emergency('STEP 2.5: USER FOUND!', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reference' => $reference
        ]);

        // -------------------- PAYSTACK API VERIFICATION (NEW) --------------------
        Log::emergency('STEP 2.6: Verifying transaction with Paystack API');

        $verified = $this->verifyTransaction($reference);

        if (!$verified) {
            Log::critical('STEP 2.7: Paystack verification failed', [
                'reference' => $reference
            ]);
            return;
        }

        if (
            ($verified['status'] ?? null) !== 'success' ||
            (($verified['amount'] ?? 0) / 100) !== $amount ||
            strtolower($verified['customer']['email'] ?? '') !== strtolower($email)
        ) {
            Log::critical('STEP 2.8: Paystack verification mismatch', [
                'reference' => $reference,
                'expected_amount' => $amount,
                'verified_amount' => ($verified['amount'] ?? 0) / 100,
                'expected_email' => $email,
                'verified_email' => $verified['customer']['email'] ?? null,
            ]);
            return;
        }

        Log::emergency('STEP 2.9: Paystack verification passed');

        // -------------------- DUPLICATE TRANSACTION CHECK --------------------
        if ($this->walletService->transactionExists($reference)) {
            Log::info('Duplicate transaction skipped', ['reference' => $reference]);
            return;
        }

        // -------------------- CREDIT WALLET --------------------
        Log::emergency('STEP 3: Starting DB transaction for credit');

        try {
            DB::transaction(function () use ($user, $amount, $reference) {
                Log::emergency('STEP 3.1: Inside transaction - calling credit()');

                $this->walletService->credit(
                    $user,
                    $amount,
                    'Wallet funding via Paystack',
                    $reference
                );

                Log::emergency('STEP 3.2: credit() completed without crash');
            });

            Log::emergency('STEP 3.3: WALLET CREDITED SUCCESSFULLY (transaction committed)!');
        } catch (\Exception $e) {
            Log::critical('STEP 3.4: WALLET CREDIT CRASHED!', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }


    public function handleTransferSuccess(array $payload): void
    {
        $reference = $payload['data']['reference'] ?? null;

        if (!$reference) {
            Log::warning('transfer.success missing reference', $payload);
            return;
        }

        $payout = PayoutRequest::where('paystack_reference', $reference)->first();

        if (!$payout) {
            Log::warning('Payout request not found', ['reference' => $reference]);
            return;
        }

        if ($payout->status === 'paid') {
            Log::info('Duplicate payout ignored (already paid)', ['reference' => $reference]);
            return;
        }

        try {
            DB::transaction(function () use ($payout, $reference) {
                $this->walletService->debit(
                    $payout->admin,
                    $payout->amount,
                    'Admin payout via Paystack',
                    $reference
                );

                $payout->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            });

            Log::info('SUCCESS: Admin payout processed', ['reference' => $reference]);
        } catch (\Exception $e) {
            Log::error('Payout processing failed', [
                'reference' => $reference,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function verifyTransaction(string $reference): ?array
    {
        try {
            $secret = config('services.paystack.secret_key');

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/{$reference}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$secret}",
                    "Content-Type: application/json",
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            if (!($result['status'] ?? false)) {
                return null;
            }

            return $result['data'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Paystack verifyTransaction failed', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}
