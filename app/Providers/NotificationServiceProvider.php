<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\DispatchBusinessNotifications;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Support\Events\BusinessEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        NotificationFacade::extend('tenant_database', fn (): TenantDatabaseChannel => app(TenantDatabaseChannel::class));

        // Same universal-wildcard pattern as the audit projection: parent
        // class listeners do not match child events in this framework.
        Event::listen('*', function (string $eventName, array $payload): void {
            $event = $payload[0] ?? null;

            if ($event instanceof BusinessEvent) {
                app(DispatchBusinessNotifications::class)->handle($event);
            }
        });
    }
}
