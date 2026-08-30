<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for event-driven notifications (handbook Ch. 35).
 *
 * Channels are preference-gated per user: `database` always (when the
 * notification implements toArray) and `mail` when a toMail exists.
 * `tenant_id` on the stored row is auto-filled from the request/job
 * context by the Notification model's tenant concern.
 */
abstract class SchoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['tenant_database'];

        if (method_exists($this, 'toMail')) {
            $channels[] = 'mail';
        }

        return array_values(array_filter(
            $channels,
            fn (string $channel): bool => NotificationPreference::isEnabled($notifiable, static::class, $channel),
        ));
    }
}
