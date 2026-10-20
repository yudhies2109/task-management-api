<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Origin harus sama persis dengan frontend yang diizinkan.
        // Request tanpa Origin juga ditolak.
        if (
            $request->header('Origin') !==
            config('tokens.frontend_origin')
        ) {
            return response()->json([
                'message' => 'Origin request tidak diizinkan.',
            ], 403);
        }

        return $next($request);
    }
}