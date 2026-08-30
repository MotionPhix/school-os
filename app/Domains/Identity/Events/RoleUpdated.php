<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\Role;
use App\Support\Events\BusinessEvent;

final class RoleUpdated extends BusinessEvent
{
    public function __construct(public readonly Role $role)
    {
        parent::__construct($role->tenant_id ?? '00000000-0000-0000-0000-000000000000');
    }

    public function name(): string
    {
        return 'identity.role.updated';
    }

    public function payload(): array
    {
        return [
            'role_id' => $this->role->id,
            'permission_keys' => $this->role->permission_keys,
        ];
    }
}
