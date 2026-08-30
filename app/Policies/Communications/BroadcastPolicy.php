<?php

declare(strict_types=1);

namespace App\Policies\Communications;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class BroadcastPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'communications.broadcasts.read');
    }

    public function view(User $user, Broadcast $b): bool
    {
        return $this->has($user, 'communications.broadcasts.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'communications.broadcasts.write');
    }

    public function update(User $user, Broadcast $b): bool
    {
        return $this->has($user, 'communications.broadcasts.write')
            && in_array($b->status, [BroadcastStatus::Draft, BroadcastStatus::Queued], true);
    }

    public function start(User $user, Broadcast $b): bool
    {
        return $this->has($user, 'communications.broadcasts.start')
            && in_array($b->status, [BroadcastStatus::Draft, BroadcastStatus::Queued], true);
    }

    public function cancel(User $user, Broadcast $b): bool
    {
        return $this->has($user, 'communications.broadcasts.cancel')
            && ! in_array($b->status, [BroadcastStatus::Completed, BroadcastStatus::Failed], true);
    }
}
