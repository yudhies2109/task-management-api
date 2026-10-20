<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Middleware\EnsureTrustedOrigin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'message' => 'Task Management API berjalan.',
        ]);
    });

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware([
            EnsureTrustedOrigin::class,
            'throttle:5,1',
        ]);

    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('auth:api');

    Route::post('/refresh-token', [AuthController::class, 'refresh'])
        ->middleware([
            EnsureTrustedOrigin::class,
            'throttle:10,1',
        ]);

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware([
            EnsureTrustedOrigin::class,
            'throttle:10,1',
        ]);
});
