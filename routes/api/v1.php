<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Billing\BillingController;
use App\Http\Controllers\Api\V1\Identity\AccountController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 entry point  (grazulex/laravel-apiroute)
|--------------------------------------------------------------------------
|
| The legacy AuthController surface is retired — its routes referenced a
| controller that no longer exists. Session lifecycle (login/logout/me/
| register) lives in Identity\SessionController and credential recovery
| in Identity\AccountController, both under /api/v1/identity (see
| routes/api/v1/identity.php). Do NOT re-add /api/v1/login|logout|me|register.
|
| The framework's VerifyEmail and ResetPassword notifications hardcode the
| route names "verification.verify" and "password.reset". The aliases at
| the bottom expose the identity endpoints under those names so mail links
| resolve. They are registered after the capability loader, so they win
| over the identical URIs from identity.php — same controllers, same
| behaviour.
|
| Capability route files in routes/api/v1/*.php are auto-loaded below.
| File name becomes both the URL prefix and route-name prefix:
|   routes/api/v1/finance.php  →  /api/v1/finance/...   name: api.v1.finance.*
|
| Do NOT also register App\Providers\CapabilityRouteServiceProvider
| (it is excluded from bootstrap/providers.php) or every capability route
| is declared twice.
|
| Files starting with `_` are ignored.
*/

$dir = base_path('routes/api/v1');

if (is_dir($dir)) {
    foreach (glob($dir.'/*.php') as $file) {
        $capability = basename($file, '.php');

        if (str_starts_with($capability, '_')) {
            continue;
        }

        Route::middleware([
            'force.json',
            'log.api',
            'auth:sanctum',
            'throttle:authenticated',
            'resolve.tenant',
            'verified',
            'idempotency',
        ])
            ->prefix($capability)
            ->name("api.v1.{$capability}.")
            ->group($file);
    }
}

// ---- Framework-compatible notification aliases -----------------------------

Route::post('identity/email/verify/{id}/{hash}', [AccountController::class, 'verifyEmail'])
    ->middleware(['force.json', 'log.api', 'auth:sanctum', 'signed'])
    ->name('verification.verify');

Route::post('identity/password/reset', [AccountController::class, 'resetPassword'])
    ->middleware(['force.json', 'log.api', 'throttle:6,1'])
    ->name('password.reset');

// ---- PayChangu IPNs (public — always re-verified server-side) --------------
// Platform billing: tenants pay the platform.
Route::post('billing/webhooks/paychangu', [BillingController::class, 'webhook'])
    ->middleware(['force.json', 'log.api', 'throttle:60,1'])
    ->name('api.v1.billing.webhooks.paychangu');

// Parents portal: parents pay the TENANT (their own PayChangu account).
Route::post('portal/webhooks/paychangu', [PortalController::class, 'webhook'])
    ->middleware(['force.json', 'log.api', 'throttle:60,1'])
    ->name('api.v1.portal.webhooks.paychangu');
