<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RecordBusinessEvent;
use App\Support\Events\BusinessEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the audit projection. A wildcard listener keeps every capability
 * covered without registering each Business Event individually.
 *
 * Register in bootstrap/providers.php:
 *   App\Providers\AuditServiceProvider::class,
 */
final class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen('*', function (string $eventName, array $payload): void {
            $event = $payload[0] ?? null;

            if ($event instanceof BusinessEvent) {
                app(RecordBusinessEvent::class)->handle($event);
            }
        });
    }
}
