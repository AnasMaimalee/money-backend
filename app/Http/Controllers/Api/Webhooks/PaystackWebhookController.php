<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Paystack\PaystackWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaystackWebhookService $service)
    {
        // 🚨 STEP 1: CRITICAL - Get UNMODIFIED raw body BEFORE Laravel touches it
        $rawBody = file_get_contents('php://input');

        // 🚨 STEP 2: Log raw body for debugging (REMOVE IN PRODUCTION)
        Log::debug('🔍 RAW PAYSTACK BODY', ['body' => $rawBody]);

        // 🚨 STEP 3: Get signature
        $signature = $request->header('x-paystack-signature');
        Log::debug('🔍 PAYSTACK HEADERS', [
            'signature' => $signature,
            'secret' => substr(config('services.paystack.secret_key'), 0, 10) . '...'
        ]);

        if (!$signature) {
            return response()->json(['status' => 'missing_signature'], Response::HTTP_UNAUTHORIZED);
        }

        // 🚨 STEP 4: VERIFY - Use EXACT Paystack secret from .env
        $secret = config('services.paystack.secret_key');
        if (!$secret || $secret === 'sk_test_xxxxxxxx') {
            Log::error('❌ PAYSTACK SECRET NOT SET IN .env');
            return response()->json(['status' => 'config_error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $computedSignature = hash_hmac('sha512', $rawBody, $secret);

        Log::debug('🔍 SIGNATURE CHECK', [
            'received' => substr($signature, 0, 32) . '...',
            'computed' => substr($computedSignature, 0, 32) . '...',
            'match' => hash_equals($computedSignature, $signature)
        ]);

        if (!hash_equals($computedSignature, $signature)) {
            Log::error('❌ INVALID PAYSTACK SIGNATURE', [
                'ip' => $request->ip(),
                'received' => $signature,
                'computed' => $computedSignature,
            ]);
            return response()->json(['status' => 'invalid_signature'], Response::HTTP_UNAUTHORIZED);
        }

        // ✅ STEP 5: SAFE TO PROCESS
        $payload = json_decode($rawBody, true);

        try {
            $service->handle($payload);
            Log::info('✅ WALLET CREDITED', ['event' => $payload['event'] ?? 'unknown']);
            return response()->json(['status' => 'success'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('💥 WEBHOOK PROCESSING FAILED', [
                'event' => $payload['event'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'processing_failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
