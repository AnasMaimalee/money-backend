<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UserService;

class PasswordController extends Controller
{
    public function update(Request $request, UserService $userService)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userService->updatePassword(
            auth()->user(),
            $request->password
        );

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }
}
