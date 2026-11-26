<?php

use Illuminate\Support\Facades\Route;
use Modules\Activities\Http\Controllers\ActivitiesController;
use Modules\Activities\Http\Middleware\RequireActivitiesCapability;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;

Route::middleware([RequireStatefulBrowser::class, 'auth:sanctum', RequireStaffSession::class])->group(function (): void {
    Route::get('activities', [ActivitiesController::class, 'index'])
        ->middleware(RequireActivitiesCapability::class.':activities.index');
    Route::get('activities/{activity}', [ActivitiesController::class, 'show'])->where('activity', '[1-9][0-9]*')
        ->middleware(RequireActivitiesCapability::class.':activities.show');
    Route::post('activities', [ActivitiesController::class, 'store'])
        ->middleware(RequireActivitiesCapability::class.':activities.store');
    Route::put('activities/{activity}', [ActivitiesController::class, 'update'])->where('activity', '[1-9][0-9]*')
        ->middleware(RequireActivitiesCapability::class.':activities.update');
    Route::delete('activities/{activity}', [ActivitiesController::class, 'destroy'])->where('activity', '[1-9][0-9]*')
        ->middleware(RequireActivitiesCapability::class.':activities.delete');
});
