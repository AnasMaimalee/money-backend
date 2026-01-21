<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFAVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        if ($user->hasRole('superadmin') && $user->google2fa_enabled) {
            if (! session('2fa_verified')) {
                return response()->json(['message' => '2FA verification required'], 403);
            }
        }
        return $next($request);
    }

}
