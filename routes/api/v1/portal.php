<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/**
 * Parents portal — guardians pay their children's fees straight to the
 * tenant's own PayChangu account. Guardians are not tenant members, so the
 * tenant is derived from the guardian/student/invoice rows instead of the
 * resolve.tenant middleware.
 */
Route::withoutMiddleware(['resolve.tenant'])->group(function (): void {
    Route::get('students', [PortalController::class, 'students']);
    Route::get('students/{student}/invoices', [PortalController::class, 'studentInvoices']);
    Route::post('invoices/{invoice}/checkout', [PortalController::class, 'checkout']);
    Route::post('payments/{intent}/refresh', [PortalController::class, 'refresh']);
});
