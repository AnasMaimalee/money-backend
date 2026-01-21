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
        $user = auth('api')->user()?->fresh();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $google2fa = new Google2FA();
        $force = $request->boolean('force', false);

        // ❌ If already confirmed and not forcing → block regeneration
        if ($user->google2fa_enabled && $user->google2fa_confirmed_at && ! $force) {
            return response()->json([
                'message' => '2FA already enabled',
                'already_enabled' => true,
            ]);
        }

        // Generate new secret ONLY when needed
        $secret = $google2fa->generateSecretKey(32);

        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(10)));

        $user->update([
            'google2fa_secret'         => $secret,
            'google2fa_enabled'        => false,
            'google2fa_confirmed_at'   => null,
            'google2fa_recovery_codes' => $recoveryCodes,
        ]);

        $otpauthUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'EduOasis'),
            $user->email,
            $secret
        );

        // SVG QR
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        $svgString = $writer->writeString($otpauthUrl);
        $base64Svg = 'data:image/svg+xml;base64,' . base64_encode($svgString);

        return response()->json([
            'qr_code'        => $base64Svg,
            'secret'         => $secret, // optional (debug only)
            'recovery_codes' => $recoveryCodes,
            'already_enabled'=> false,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $user = auth('api')->user()?->fresh();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 🔎 Hard safety checks
        if (
            empty($user->google2fa_secret) ||
            strlen($user->google2fa_secret) < 16
        ) {
            return response()->json([
                'message' => '2FA setup incomplete. Please generate a new QR code.',
            ], 422);
        }

        // 🔐 Verify with safe window (±2 = 60 seconds)
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->google2fa_secret,
            $request->code,
            2
        );

        if (! $valid) {
            return response()->json([
                'message' => 'Invalid 2FA code. Ensure your phone time is correct.',
            ], 422);
        }

        // ✅ RECORD SUCCESS (THIS WAS MISSING)
        $user->update([
            'google2fa_enabled'        => true,
            'google2fa_confirmed_at'   => now(),
            'google2fa_last_used_at'   => now(),
        ]);

        \Log::info('2FA enabled successfully', [
            'user_id' => $user->id,
            'confirmed_at' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'message' => '2FA enabled successfully',
            'enabled' => true,
            'confirmed_at' => $user->google2fa_confirmed_at,
        ]);
    }

}
