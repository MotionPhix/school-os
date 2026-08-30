<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\Announcement;
use App\Support\Events\BusinessEvent;

final class AnnouncementDrafted extends BusinessEvent
{
    public function __construct(public readonly Announcement $announcement)
    {
        parent::__construct($announcement->tenant_id);
    }

    public function name(): string
    {
        return 'communications.announcement.drafted';
    }

    public function payload(): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'audience' => $this->announcement->audience->value,
            'status' => $this->announcement->status->value,
        ];
    }
}
