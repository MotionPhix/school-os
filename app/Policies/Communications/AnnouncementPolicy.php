<?php

declare(strict_types=1);

namespace App\Policies\Communications;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class AnnouncementPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'communications.announcements.read');
    }

    public function view(User $user, Announcement $ann): bool
    {
        return $this->has($user, 'communications.announcements.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'communications.announcements.write');
    }

    public function update(User $user, Announcement $ann): bool
    {
        return $this->has($user, 'communications.announcements.write')
            && in_array($ann->status, [AnnouncementStatus::Draft, AnnouncementStatus::Scheduled], true);
    }

    public function send(User $user, Announcement $ann): bool
    {
        return $this->has($user, 'communications.announcements.send')
            && $ann->status !== AnnouncementStatus::Archived;
    }

    public function archive(User $user, Announcement $ann): bool
    {
        return $this->has($user, 'communications.announcements.archive');
    }
}
