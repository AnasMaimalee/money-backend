<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminPayoutService;
use Illuminate\Http\Request;

class AdminPayoutController extends Controller
{
    public function __construct(
        protected AdminPayoutService $payoutService
    ) {
        $this->middleware(['auth:api', 'role:administrator']);
    }

    public function payout(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $transaction = $this->payoutService->payout(
            auth()->user(),
            $validated['amount']
        );

        return response()->json([
            'message' => 'Payout initiated successfully',
            'transaction' => [
                'id'        => $transaction->id,
                'amount'    => $transaction->amount,
                'reference' => $transaction->reference,
            ],
        ]);
    }
}
