<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastServiceProvider as BaseBroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;

/**
 * Registers the broadcast channel-authorization endpoint for the API
 * (Sanctum token auth + the standard JSON error envelope) and loads the
 * channel definitions from routes/channels.php.
 *
 * Register in bootstrap/providers.php:
 *   App\Providers\BroadcastServiceProvider::class,
 */
final class BroadcastServiceProvider extends BaseBroadcastServiceProvider
{
    public function boot(): void
    {
        // resolve.tenant derives the active tenant (X-Tenant-Id header) so
        // channel-authorization callbacks can check permission keys against
        // the caller's membership roles; throttle guards the auth endpoint
        // against socket-client subscribe storms.
        Broadcast::routes(['middleware' => ['auth:sanctum', 'force.json', 'resolve.tenant', 'throttle:broadcasting']]);

        require base_path('routes/channels.php');
    }
}
