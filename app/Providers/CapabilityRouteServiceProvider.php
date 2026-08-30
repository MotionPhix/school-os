<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Auto-loads every file under routes/api/v1/*.php as a capability route
 * group. File name becomes the URL prefix and route name prefix.
 *
 *   routes/api/v1/identity.php  →  /api/v1/identity/...   name: api.v1.identity.*
 *   routes/api/v1/institution.php → /api/v1/institution/... name: api.v1.institution.*
 *
 * Files starting with `_` (e.g. `_meta.php`) are ignored — useful for
 * stubs or shared helpers.
 *
 * Every capability route group gets `auth:sanctum`, `throttle:authenticated`,
 * and `resolve.tenant` middleware by default (aliases registered in bootstrap/app.php). Override per-route with ->withoutMiddleware().
 */
final class CapabilityRouteServiceProvider extends RouteServiceProvider
{
    public function boot(): void
    {
        // If grazulex/laravel-apiroute is mounting routes/api/v1.php, that file
        // already loads every capability file. Registering them here too would
        // duplicate every route, so bail out.
        if (config('apiroute.versions.v1.routes') !== null) {
            return;
        }

        $this->routes(function (): void {
            $dir = base_path('routes/api/v1');
            if (! is_dir($dir)) {
                return;
            }

            foreach (glob($dir.'/*.php') as $file) {
                $capability = basename($file, '.php');
                if (str_starts_with($capability, '_')) {
                    continue;
                }

                Route::middleware([
                    'api',
                    'force.json',
                    'log.api',
                    'auth:sanctum',
                    'throttle:authenticated',
                    'resolve.tenant',
                ])
                    ->prefix("api/v1/{$capability}")
                    ->name("api.v1.{$capability}.")
                    ->group($file);
            }
        });
    }
}
