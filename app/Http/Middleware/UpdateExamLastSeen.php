<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateExamLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()) {
            // Update user's last seen timestamp for CBT
            Auth::user()->update([
                'last_seen_at' => now()
            ]);
        }

        return $next($request);
    }
}
