<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\Guardian;
use App\Support\Events\BusinessEvent;

final class GuardianCreated extends BusinessEvent
{
    public function __construct(public readonly Guardian $guardian)
    {
        parent::__construct($guardian->tenant_id);
    }

    public function name(): string
    {
        return 'people.guardian.created';
    }

    public function payload(): array
    {
        return [
            'guardian_id' => $this->guardian->id,
            'portal_status' => $this->guardian->portal_status->value,
        ];
    }
}
