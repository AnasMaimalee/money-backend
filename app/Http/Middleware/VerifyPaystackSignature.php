<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyPaystackSignature
{
    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('x-paystack-signature');

        $computed = hash_hmac(
            'sha512',
            $request->getContent(),
            config('services.paystack.secret')
        );

        abort_unless(hash_equals($signature, $computed), 403);

        return $next($request);
    }
}
