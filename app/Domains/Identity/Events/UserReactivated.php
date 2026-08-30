<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\User;
use App\Support\Events\BusinessEvent;

final class UserReactivated extends BusinessEvent
{
    public function __construct(
        public readonly User $user,
        string $tenantId,
        public readonly string $actorId,
    ) {
        parent::__construct($tenantId);
    }

    public function name(): string
    {
        return 'identity.user.reactivated';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->id,
            'actor_id' => $this->actorId,
        ];
    }
}
