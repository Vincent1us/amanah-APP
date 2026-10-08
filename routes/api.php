<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\WhatsappLoginController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', fn () => ApiResponse::success(['status' => 'ok', 'time' => now()->toIso8601String()]));

    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('reset-password', [PasswordResetController::class, 'reset']);
        });

        Route::middleware('throttle:otp')->group(function () {
            Route::post('login/whatsapp/request-otp', [WhatsappLoginController::class, 'requestOtp']);
            Route::post('login/whatsapp/verify-otp', [WhatsappLoginController::class, 'verifyOtp']);
            Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
            Route::post('forgot-password/verify-otp', [PasswordResetController::class, 'verifyOtp']);
            Route::post('otp/resend', [OtpController::class, 'resend']);
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });
});
