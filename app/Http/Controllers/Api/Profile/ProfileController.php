<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Profile\ProfileService;

class ProfileController extends Controller
{
    public function __construct(protected ProfileService $service) {}

    // Get user profile + bank account
    public function show()
    {
        $user = auth()->user();

        $profile = $this->service->profile($user);

        return response()->json([
            'success' => true,
            'message' => 'Profile data fetched successfully',
            'data' => $profile
        ]);
    }

    public function updateBank(Request $request)
    {
        $request->validate([
            'bank_name'      => 'required|string',
            'account_name'   => 'required|string',
            'account_number' => 'required|string|min:10',
        ]);

        // POST will either create or update
        $account = $this->service->updateBank(auth()->user(), $request->only([
            'bank_name', 'account_name', 'account_number'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Bank account saved successfully',
            'data' => $account['data']
        ]);
    }


    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $this->service->updatePassword(
            auth()->user(),
            $request->current_password,
            $request->password
        );

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }

}
