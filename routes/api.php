<?php

use Illuminate\Support\Facades\Route;

require base_path('Modules/Identity/routes/api.php');

// A testing-only probe: never published in production route registration.
if (app()->runningUnitTests()) {
    Route::get('/__foundation-route-probe', fn () => response()->json(['success' => true]));
}
