<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\WalletService;
use App\Models\User;

class PaymentController extends Controller
{

    /**
     * Initialize wallet funding (frontend uses this data)
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $user = auth()->user();
        $reference = (string) Str::uuid();

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $request->amount * 100,
                'reference' => $reference,
            ]);

        if (!$response->ok()) {
            return response()->json([
                'message' => 'Unable to initialize payment'
            ], 400);
        }

        return response()->json($response->json());
    }


    /**
     * Verify payment and credit wallet
     */
    public function verify(Request $request, WalletService $walletService)
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $reference = $request->reference;

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (!$response->ok()) {
            return response()->json([
                'message' => 'Unable to verify payment'
            ], 400);
        }

        $data = $response->json('data');

        if ($data['status'] !== 'success') {
            return response()->json([
                'message' => 'Payment not successful'
            ], 400);
        }

        $amount = $data['amount'] / 100; // convert from kobo
        $user = auth()->user();

        $walletService->credit(
            $user,
            $amount,
            $reference,
            'Wallet funding via Paystack'
        );

        return response()->json([
            'message' => 'Wallet funded successfully'
        ]);
    }
}
