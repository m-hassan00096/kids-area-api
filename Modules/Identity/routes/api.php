<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\AuthController;
use Modules\Identity\Http\Controllers\ProfileController;
use Modules\Identity\Http\Controllers\RolesController;
use Modules\Identity\Http\Controllers\UsersController;
use Modules\Identity\Http\Middleware\NormalizeUsersInput;
use Modules\Identity\Http\Middleware\RequireRolesCapability;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\Identity\Http\Middleware\RequireUsersCapability;

Route::middleware(RequireStatefulBrowser::class)->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum', RequireStaffSession::class])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('profile/password', [ProfileController::class, 'password']);

        Route::get('roles', [RolesController::class, 'index'])
            ->middleware(RequireRolesCapability::class.':identity.roles.index');
        Route::get('roles/{role}', [RolesController::class, 'show'])->where('role', '[1-9][0-9]*')
            ->middleware(RequireRolesCapability::class.':identity.roles.show');
        Route::post('roles', [RolesController::class, 'store'])
            ->middleware(RequireRolesCapability::class.':identity.roles.store');
        Route::put('roles/{role}', [RolesController::class, 'update'])->where('role', '[1-9][0-9]*')
            ->middleware(RequireRolesCapability::class.':identity.roles.update');
        Route::delete('roles/{role}', [RolesController::class, 'destroy'])->where('role', '[1-9][0-9]*')
            ->middleware(RequireRolesCapability::class.':identity.roles.delete');

        Route::get('users', [UsersController::class, 'index'])
            ->middleware([RequireUsersCapability::class.':identity.users.view', NormalizeUsersInput::class]);
        Route::post('users', [UsersController::class, 'store'])
            ->middleware([RequireUsersCapability::class.':identity.users.create', NormalizeUsersInput::class]);
        Route::prefix('users/{user}')->where(['user' => '[1-9][0-9]*'])->group(function (): void {
            Route::get('/', [UsersController::class, 'show'])
                ->middleware([RequireUsersCapability::class.':identity.users.view', NormalizeUsersInput::class]);
            Route::put('/', [UsersController::class, 'update'])
                ->middleware([RequireUsersCapability::class.':identity.users.update', NormalizeUsersInput::class]);
            Route::delete('/', [UsersController::class, 'destroy'])
                ->middleware([RequireUsersCapability::class.':identity.users.delete', NormalizeUsersInput::class]);
            Route::get('avatar', [UsersController::class, 'avatar'])
                ->middleware([RequireUsersCapability::class.':identity.users.view', NormalizeUsersInput::class]);
        });
    });
});
