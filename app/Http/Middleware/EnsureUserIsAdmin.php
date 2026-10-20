<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Mengambil pengguna yang telah diautentikasi melalui guard API.
        $user = $request->user('api');

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Pengguna yang sudah login tetapi bukan admin ditolak.
        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Akses hanya diizinkan untuk admin.',
            ], 403);
        }

        // Admin diizinkan melanjutkan request.
        return $next($request);
    }
}
