<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Auth\MeController;
use App\Services\Auth\WebAuthnService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebauthnController extends Controller
{
    public function __construct(
        protected WebAuthnService $webauthn,
        protected MeController $meController
    ) {}

    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'publicKey' => $this->webauthn->registerOptions($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $this->webauthn->register($request);

            return response()->json([
                'message' => 'Passkey registered successfully',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('WebAuthn register error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Registration failed'], 400);
        }
    }

    public function loginOptions(): JsonResponse
    {
        return response()->json([
            'publicKey' => $this->webauthn->loginOptions(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        try {
            // 1️⃣ Authenticate user via WebAuthn
            $user = $this->webauthn->login($request);

            // 2️⃣ Generate JWT token
            $token = auth('api')->login($user);

            // 3️⃣ Force API guard (Spatie safety)
            config(['auth.defaults.guard' => 'api']);

            // 4️⃣ Load relations
            $user->load(['wallet']);

            // 5️⃣ Get menus using SAME logic as normal login
            $menus = $this->meController->getMenusForUser($user);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role'  => $user->getRoleNames()->first() ?? 'user',
                ],
                'menus' => $menus,
            ]);
        } catch (\Exception $e) {
            Log::error('WebAuthn login failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Authentication failed'
            ], 401);
        }
    }



    public function index(Request $request): JsonResponse
    {
        $credentials = $this->webauthn->credentials($request->user());

        return response()->json([
            'hasCredential' => $credentials->isNotEmpty(),
            'credentials' => $credentials->map(fn ($c) => [
                'id' => $c->credential_id,
                'name' => $c->alias,
                'created_at' => $c->created_at->toDateTimeString(),
            ]),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $deleted = $this->webauthn->destroy(
            $request->user(),
            $request->input('credential_id')
        );

        return response()->json([
            'message' => $deleted ? 'Passkey removed' : 'No passkey found',
        ]);
    }
}
