<?php

namespace App\Http\Controllers\Api\Payout;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Services\Payout\AdminPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPayoutController extends Controller
{
    // Superadmin sees all payout requests
    public function listRequests(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = PayoutRequest::with('administrator')->latest();

        if ($user->hasRole('administrator')) {
            $query->where('admin_id', $user->id);
        }

        $payouts = $query->paginate(20);

        return response()->json([
            'message' => 'Payout requests retrieved successfully',
            'data'    => $payouts,
        ]);
    }

    // Admin sees only their own payout requests
    public function myPayoutRequests(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user->hasRole('administrator')) {
            abort(403, 'Only administrators can view their payout requests');
        }

        $payouts = PayoutRequest::with('administrator')
            ->where('admin_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'My payout requests retrieved successfully',
            'data'    => $payouts,
        ]);
    }

    /**
     * Admin requests payout
     */
    public function requestPayout(
        Request $request,
        AdminPayoutService $service
    ): JsonResponse {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $payout = $service->request(
            auth()->user(),
            $validated['amount']
        );

        return response()->json([
            'message' => 'Payout request submitted successfully',
            'data'    => $payout,
        ], 201);
    }

    /**
     * Superadmin approves & triggers Paystack payout
     */
    public function approvePayout(
        PayoutRequest $payout,
        AdminPayoutService $service
    ): JsonResponse {
        $result = $service->approveAndPay(
            $payout,
            auth()->user()
        );

        return response()->json([
            'message' => 'Payout approved and processed successfully',
            'data'    => $result,
        ]);
    }
}
