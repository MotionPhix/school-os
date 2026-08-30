<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Support\TenantContext;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, extended to stamp `tenant_id` from the
 * request/job context (the stock channel instantiates the base
 * DatabaseNotification, so the model's tenant concern never fires).
 */
final class TenantDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'tenant_id' => app(TenantContext::class)->id(),
        ];
    }
}
