<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
| All routes here are served under /api/v1 (see bootstrap/app.php).
| The contract for every endpoint lives in openapi.yaml at the repo root.
*/

Route::prefix('auth')->group(function () {
    Route::middleware(['throttle:auth', 'device'])->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('google', [AuthController::class, 'google']);
    });

    Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['throttle:refresh', 'device']);

    Route::middleware('throttle:auth')->group(function () {
        Route::post('password/forgot', [PasswordResetController::class, 'forgot']);
        Route::post('password/reset', [PasswordResetController::class, 'reset']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        Route::middleware('throttle:auth')->group(function () {
            Route::post('email/send-code', [EmailVerificationController::class, 'send']);
            Route::post('email/verify', [EmailVerificationController::class, 'verify']);
        });
    });
});

Route::middleware('auth:api')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    Route::delete('me', [MeController::class, 'destroy']);
});
