<?php

declare(strict_types=1);

namespace App\Domains\Institution\Events;

use App\Models\InstitutionProfile;
use App\Support\Events\BusinessEvent;

final class InstitutionProfileUpdated extends BusinessEvent
{
    public function __construct(public readonly InstitutionProfile $profile)
    {
        parent::__construct($profile->tenant_id);
    }

    public function name(): string
    {
        return 'institution.profile.updated';
    }

    public function payload(): array
    {
        return [
            'tenant_id' => $this->profile->tenant_id,
            'name' => $this->profile->name,
            'type' => $this->profile->type->value,
        ];
    }
}
