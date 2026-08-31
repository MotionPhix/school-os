<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Billing\BillingController;
use App\Http\Controllers\Api\V1\Billing\PaymentProviderController;
use Illuminate\Support\Facades\Route;

Route::get('overview', [BillingController::class, 'overview']);
Route::post('invoices/{invoice}/checkout', [BillingController::class, 'checkout']);
Route::post('payments/{payment}/refresh', [BillingController::class, 'refresh']);

// The tenant's OWN PayChangu credentials (money lands in their bank).
Route::get('provider', [PaymentProviderController::class, 'show']);
Route::put('provider', [PaymentProviderController::class, 'update']);
