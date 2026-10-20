<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'message' => 'Task Management API berjalan.',
        ]);
    });

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');
});