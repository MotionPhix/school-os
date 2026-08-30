<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\Tenant;
use App\Support\Events\BusinessEvent;

final class TenantCreated extends BusinessEvent
{
    public function __construct(
        public readonly Tenant $tenant,
    ) {
        parent::__construct($tenant->id);
    }

    public function name(): string
    {
        return 'identity.tenant.created';
    }

    public function payload(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'slug' => $this->tenant->slug,
            'tier' => $this->tenant->tier->value,
        ];
    }
}
