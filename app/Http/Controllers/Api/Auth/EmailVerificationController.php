<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{
    // Verify email
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill(); // marks email as verified
        return response()->json(['message' => 'Email verified successfully']);
    }

    // Resend verification email
    public function resend(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification link sent!']);
    }
}
