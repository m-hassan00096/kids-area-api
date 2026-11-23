<?php

use Illuminate\Support\Facades\Route;

// A testing-only probe: never published in production route registration.
if (app()->runningUnitTests()) {
    Route::get('/__foundation-route-probe', fn () => response()->json(['success' => true]));
}
