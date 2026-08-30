<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\Announcement;
use App\Support\Events\BusinessEvent;

final class AnnouncementArchived extends BusinessEvent
{
    public function __construct(public readonly Announcement $announcement)
    {
        parent::__construct($announcement->tenant_id);
    }

    public function name(): string
    {
        return 'communications.announcement.archived';
    }

    public function payload(): array
    {
        return ['announcement_id' => $this->announcement->id];
    }
}
