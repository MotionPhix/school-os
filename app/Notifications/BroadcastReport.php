<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Communications\Events\BroadcastCompleted;

/** In-app delivery for BroadcastCompleted (see config/notifications.php). */
final class BroadcastReport extends SchoolNotification
{
    public function __construct(private readonly BroadcastCompleted $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $broadcast = $this->event->broadcast;

        return [
            'kind' => 'communications',
            'title' => "Broadcast delivered — {$broadcast->name}",
            'body' => sprintf(
                '%d/%d delivered · %d failed',
                $broadcast->delivered_count,
                $broadcast->recipient_count,
                $broadcast->failed_count,
            ),
            'href' => "/communications/broadcasts/{$broadcast->id}",
        ];
    }
}
