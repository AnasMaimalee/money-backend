<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaystackIP
{
    protected array $allowedIps = [
        '52.31.139.75',
        '52.49.173.169',
        '52.214.14.220',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->ip(), $this->allowedIps, true)) {
            return response()->json(
                ['message' => 'Unauthorized source'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }
}
