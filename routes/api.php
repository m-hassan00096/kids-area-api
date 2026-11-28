<?php

use App\Http\Controllers\SelectController;
use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;

require base_path('Modules/Identity/routes/api.php');
require base_path('Modules/Activities/routes/api.php');
require base_path('Modules/People/routes/api.php');

Route::middleware([RequireStatefulBrowser::class, 'auth:sanctum', RequireStaffSession::class])
    ->get('selects', SelectController::class);

// A testing-only probe: never published in production route registration.
if (app()->runningUnitTests()) {
    Route::get('/__foundation-route-probe', fn () => response()->json(['success' => true]));
}
