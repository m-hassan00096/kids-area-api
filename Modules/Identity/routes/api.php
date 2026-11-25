<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\AuthController;
use Modules\Identity\Http\Controllers\ProfileController;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\Identity\Http\Middleware\RequireStaffSession;

Route::middleware(RequireStatefulBrowser::class)->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum', RequireStaffSession::class])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('profile/password', [ProfileController::class, 'password']);
    });
});
