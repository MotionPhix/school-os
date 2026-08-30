<?php

declare(strict_types=1);

namespace App\Policies\Communications;

use App\Models\MessageThread;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class MessageThreadPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'communications.threads.read');
    }

    public function view(User $user, MessageThread $t): bool
    {
        return $this->has($user, 'communications.threads.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'communications.threads.write');
    }

    public function reply(User $user, MessageThread $t): bool
    {
        return $this->has($user, 'communications.threads.write');
    }

    public function update(User $user, MessageThread $t): bool
    {
        return $this->has($user, 'communications.threads.write');
    }
}
