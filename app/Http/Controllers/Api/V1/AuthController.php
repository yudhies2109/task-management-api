<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    /**
     * Memvalidasi input dan membuat akun pengguna.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Menerbitkan JWT access token dan cookie refresh token.
     */
    public function login(
        LoginRequest $request,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        $credentials = $request->validated();

        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        $token = $guard->attempt($credentials);

        if (! $token) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $user = $guard->user();

        // Membuat refresh token dan menyimpan hash-nya di database.
        $refreshToken = $refreshTokenService->issue($user);

        // Cookie dikirim ke semua endpoint dengan path /api/v1.
        $cookie = cookie(
            'refresh_token',
            $refreshToken['token'],
            config('tokens.refresh_ttl_days') * 24 * 60,
            '/api/v1',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ])
            ->header('Cache-Control', 'no-store')
            ->withCookie($cookie);
    }

    /**
     * Mengganti refresh token dan menerbitkan JWT baru.
     */
    public function refresh(
        Request $request,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        // Mengambil refresh token yang dikirim melalui cookie.
        $plainToken = $request->cookie('refresh_token');

        // Tolak cookie kosong atau format token yang tidak sesuai.
        if (
            ! is_string($plainToken) ||
            ! preg_match('/\A[a-f0-9]{64}\z/', $plainToken)
        ) {
            return response()->json([
                'message' => 'Refresh token tidak valid.',
            ], 401);
        }

        // Service memvalidasi token dan melakukan rotasi.
        $result = $refreshTokenService->rotate($plainToken);

        if ($result === null) {
            return response()->json([
                'message' => 'Refresh token tidak valid atau sudah tidak berlaku.',
            ], 401)
                ->header('Cache-Control', 'no-store')
                ->withCookie(
                    cookie()->forget('refresh_token', '/api/v1')
                );
        }

        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        // Menerbitkan JWT berdasarkan pengguna pemilik refresh token.
        $accessToken = $guard->login($result['user']);

        // Cookie baru mengikuti sisa masa berlaku refresh token.
        $minutes = max(
            1,
            (int) ceil(
                now()->diffInSeconds($result['expires_at'], false) / 60
            )
        );

        $cookie = cookie(
            'refresh_token',
            $result['token'],
            $minutes,
            '/api/v1',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );

        return response()->json([
            'message' => 'Token berhasil diperbarui.',
            'data' => [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ])
            ->header('Cache-Control', 'no-store')
            ->withCookie($cookie);
    }

    /**
     * Mengembalikan informasi pengguna yang sedang login.
     */
    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Mencabut refresh token dan menghapus cookie.
     */
    public function logout(
        Request $request,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        $plainToken = $request->cookie('refresh_token');

        if (
            is_string($plainToken) &&
            preg_match('/\A[a-f0-9]{64}\z/', $plainToken)
        ) {
            $refreshTokenService->revoke($plainToken);
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ])
            ->header('Cache-Control', 'no-store')
            ->withCookie(
                cookie()->forget('refresh_token', '/api/v1')
            );
    }
}