<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
     * Memeriksa kredensial dan menerbitkan JWT access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
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
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
