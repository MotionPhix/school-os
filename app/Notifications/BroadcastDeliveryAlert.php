<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Communications\Events\BroadcastDeliveryFailureDetected;

/** In-app delivery for BroadcastDeliveryFailureDetected (see config/notifications.php). */
final class BroadcastDeliveryAlert extends SchoolNotification
{
    public function __construct(private readonly BroadcastDeliveryFailureDetected $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $broadcast = $this->event->broadcast;

        return [
            'kind' => 'system',
            'title' => "Broadcast delivery failure — {$broadcast->name}",
            'body' => sprintf(
                '%d of %d recipients failed (%d delivered)',
                (int) $broadcast->failed_count,
                (int) $broadcast->recipient_count,
                (int) $broadcast->delivered_count,
            ),
            'href' => "/communications/broadcasts/{$broadcast->id}",
        ];
    }
}
