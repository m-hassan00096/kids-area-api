<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\People\Http\Controllers\CustomerController;
use Modules\People\Http\Middleware\RequireCustomerCapability;

Route::middleware([RequireStatefulBrowser::class, 'auth:sanctum', RequireStaffSession::class])->group(function (): void {
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->where('customer', '[1-9][0-9]*')
        ->middleware(RequireCustomerCapability::class.':people.customers.show');
    Route::post('customers', [CustomerController::class, 'store'])
        ->middleware(RequireCustomerCapability::class.':people.customers.store');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->where('customer', '[1-9][0-9]*')
        ->middleware(RequireCustomerCapability::class.':people.customers.update');
});
