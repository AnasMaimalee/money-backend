<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebAuthnService
{
    /**
     * Extract RP ID from FRONTEND_URL
     */
    private function getRpId(): string
    {
        $frontendUrl = config('webauthn.frontend_url') ?? env('FRONTEND_URL');

        if (!$frontendUrl) {
            throw new \RuntimeException('FRONTEND_URL is not defined');
        }

        $host = parse_url($frontendUrl, PHP_URL_HOST);

        if (!$host) {
            throw new \RuntimeException('Invalid FRONTEND_URL');
        }

        // ✅ Localhost allowed
        if (in_array($host, ['localhost', '127.0.0.1'])) {
            return $host;
        }

        // ✅ Cloudflare tunnels (must be full domain)
        if (str_ends_with($host, '.trycloudflare.com')) {
            return $host;
        }

        // ✅ Vercel / Netlify / any hosted platform → FULL hostname
        return $host;
    }


    /**
     * Registration options
     */
    public function registerOptions(User $user): array
    {
        return [
            'rp' => [
                'name' => config('app.name', 'EduOasis'),
                'id'   => $this->getRpId(),
            ],
            'user' => [
                'id'          => base64_encode((string) $user->getKey()),
                'name'        => $user->email,
                'displayName' => $user->name ?? $user->email,
            ],
            'challenge' => base64_encode(random_bytes(32)),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'authenticatorSelection' => [
                'residentKey'     => 'preferred',
                'userVerification'=> 'discouraged',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
        ];
    }

    /**
     * Save credential
     */
    public function register(Request $request): void
    {
        $user = $request->user();

        $data = $request->validate([
            'id' => 'required|string',
            'type' => 'required|in:public-key',
            'rawId' => 'required|string',
            'response' => 'required|array',
        ]);

        WebAuthnCredential::create([
            'id' => (string) Str::uuid(),
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'credential_id' => $data['id'],
            'alias' => 'Biometric Device',
            'counter' => 0,
            'rp_id' => $this->getRpId(),
        ]);
    }

    /**
     * Login options
     */
    public function loginOptions(): array
    {
        $rpId = $this->getRpId();

        $credentials = WebAuthnCredential::where('rp_id', $rpId)
            ->pluck('credential_id')
            ->toArray();

        return [
            'challenge' => base64_encode(random_bytes(32)),
            'rpId' => $rpId,
            'timeout' => 60000,
            'userVerification' => 'discouraged',
            'allowCredentials' => array_map(fn ($id) => [
                'type' => 'public-key',
                'id' => $id,
            ], $credentials),
        ];
    }

    /**
     * Verify login
     */
    public function login(Request $request): User
    {
        $data = $request->validate([
            'id' => 'required|string',
            'type' => 'required|in:public-key',
        ]);

        $credential = WebAuthnCredential::where('credential_id', $data['id'])
            ->where('rp_id', $this->getRpId())
            ->firstOrFail();

        $credential->increment('counter');

        return $credential->user;
    }

    /**
     * List credentials
     */
    public function credentials(User $user)
    {
        return WebAuthnCredential::where('authenticatable_id', $user->id)
            ->where('rp_id', $this->getRpId())
            ->latest()
            ->get();
    }

    /**
     * Delete credential
     */
    public function destroy(User $user, ?string $credentialId): bool
    {
        return WebAuthnCredential::where('authenticatable_id', $user->id)
            ->where('rp_id', $this->getRpId())
            ->when($credentialId, fn ($q) =>
                $q->where('credential_id', $credentialId)
            )
            ->delete() > 0;
    }
}
