<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;

// BaconQrCode imports
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    /**
     * Setup 2FA for the user
     */
    public function setup(Request $request)
{
    $user = auth('api')->user()->fresh();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $google2fa = new Google2FA();

    $force = $request->boolean('force', false);

    // If already enabled and not forcing regenerate → return current values safely
    if ($user->google2fa_enabled && !$force) {
        $secret = $user->google2fa_secret;
        $recoveryCodes = $user->google2fa_recovery_codes ?? collect();
        $messageExtra = ['already_enabled' => true];
    } else {
        // Generate fresh only when needed
        $secret = $google2fa->generateSecretKey(32);
        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(10)));

        $user->update([
            'google2fa_secret'         => $secret,
            'google2fa_enabled'        => false,
            'google2fa_recovery_codes' => $recoveryCodes,
        ]);

        $messageExtra = ['already_enabled' => false];
    }

    $otpauthUrl = $google2fa->getQRCodeUrl(
        config('app.name', 'EduOasis'),
        $user->email,
        $secret
    );

    $renderer = new ImageRenderer(
        new RendererStyle(300),
        new SvgImageBackEnd()
    );
    $writer = new Writer($renderer);
    $svgString = $writer->writeString($otpauthUrl);
    $base64Svg = 'data:image/svg+xml;base64,' . base64_encode($svgString);

    return response()->json(array_merge([
        'qr_code'        => $base64Svg,
        'secret'         => $secret,
        'recovery_codes' => $recoveryCodes,
        'otpauth_url'    => $otpauthUrl, // optional - for debug
    ], $messageExtra));
}
    public function confirm(Request $request)
{
    \Log::info('2FA confirm attempt', [
        'raw_code'    => $request->input('code'),
        'code_length' => strlen($request->input('code') ?? ''),
        'is_digits'   => ctype_digit($request->input('code') ?? ''),
        'user_id'     => auth('api')->id(),
    ]);

    $request->validate([
        'code' => 'required|digits:6',
    ]);

    $userId = auth('api')->id();

    // 🔴 IMPORTANT: reload user DIRECTLY from DB
    $user = \App\Models\User::where('id', $userId)->first();

    if (!$user || empty($user->google2fa_secret) || strlen($user->google2fa_secret) < 16) {
        \Log::warning('2FA confirm failed - missing/invalid secret', [
            'user_id' => $userId,
            'secret_length' => strlen($user->google2fa_secret ?? ''),
            'has_secret' => !empty($user->google2fa_secret),
        ]);

        return response()->json([
            'message' => '2FA setup incomplete. Please generate QR code again and scan it fresh.',
            'reason'  => 'missing_secret',
        ], 422);
    }

    $google2fa = new Google2FA();

    $valid = $google2fa->verifyKey(
        $user->google2fa_secret,
        $request->code,
        1 // ±1 time window
    );

    if (!$valid) {
        return response()->json([
            'message' => 'Invalid 2FA code. Ensure your phone time is correct.',
        ], 422);
    }

    $user->google2fa_enabled = true;
    $user->save();

    return response()->json([
        'message' => '2FA enabled successfully',
    ]);
}
}
