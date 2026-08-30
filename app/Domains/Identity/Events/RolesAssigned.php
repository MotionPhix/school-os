<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Support\Events\BusinessEvent;

final class RolesAssigned extends BusinessEvent
{
    /**
     * @param  list<string>  $roleIds
     */
    public function __construct(
        string $tenantId,
        public readonly string $userId,
        public readonly array $roleIds,
        public readonly string $actorId,
    ) {
        parent::__construct($tenantId);
    }

    public function name(): string
    {
        return 'identity.roles.assigned';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'role_ids' => $this->roleIds,
            'actor_id' => $this->actorId,
        ];
    }
}
