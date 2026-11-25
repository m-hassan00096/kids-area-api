<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\AuthController;
use Modules\Identity\Http\Controllers\ProfileController;
use Modules\Identity\Http\Controllers\UsersController;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;

Route::middleware(RequireStatefulBrowser::class)->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum', RequireStaffSession::class])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('profile/password', [ProfileController::class, 'password']);

        Route::get('users', [UsersController::class, 'index']);
        Route::post('users', [UsersController::class, 'store']);
        Route::prefix('users/{user}')->where(['user' => '[1-9][0-9]*'])->group(function (): void {
            Route::get('/', [UsersController::class, 'show']);
            Route::put('/', [UsersController::class, 'update']);
            Route::delete('/', [UsersController::class, 'destroy']);
            Route::get('avatar', [UsersController::class, 'avatar']);
        });
    });
});
