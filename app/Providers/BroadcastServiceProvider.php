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
        Broadcast::routes(['middleware' => ['auth:sanctum', 'force.json']]);

        require base_path('routes/channels.php');
    }
}
