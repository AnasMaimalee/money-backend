<?php

namespace App\Http\Controllers\Api\Payout;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PayoutTestController extends Controller
{
    public function seed(): JsonResponse
    {
        $admin = User::role('administrator')->firstOrFail();

        $payouts = PayoutRequest::factory()
            ->count(5)
            ->create([
                'admin_id' => $admin->id,
            ]);

        return response()->json([
            'message' => 'Payout requests seeded successfully',
            'data' => $payouts,
        ]);
    }
}
