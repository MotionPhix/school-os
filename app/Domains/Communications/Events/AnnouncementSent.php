<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\Announcement;
use App\Support\Events\BusinessEvent;

final class AnnouncementSent extends BusinessEvent
{
    public function __construct(public readonly Announcement $announcement)
    {
        parent::__construct($announcement->tenant_id);
    }

    public function name(): string
    {
        return 'communications.announcement.sent';
    }

    public function payload(): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'recipient_count' => (int) $this->announcement->recipient_count,
            'delivered_count' => (int) $this->announcement->delivered_count,
            'channels' => $this->announcement->channels,
        ];
    }
}
