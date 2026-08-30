<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Communications\Events\AnnouncementSent as AnnouncementSentEvent;

/** In-app delivery for AnnouncementSent (see config/notifications.php). */
final class AnnouncementSent extends SchoolNotification
{
    public function __construct(private readonly AnnouncementSentEvent $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $announcement = $this->event->announcement;

        return [
            'kind' => 'announcement',
            'title' => "New announcement: {$announcement->title}",
            'body' => mb_strimwidth((string) $announcement->body, 0, 160, '…'),
            'href' => "/communications/announcements/{$announcement->id}",
        ];
    }
}
