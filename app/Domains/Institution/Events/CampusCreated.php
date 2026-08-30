<?php

declare(strict_types=1);

namespace App\Domains\Institution\Events;

use App\Models\Campus;
use App\Support\Events\BusinessEvent;

final class CampusCreated extends BusinessEvent
{
    public function __construct(public readonly Campus $campus)
    {
        parent::__construct($campus->tenant_id);
    }

    public function name(): string
    {
        return 'institution.campus.created';
    }

    public function payload(): array
    {
        return [
            'campus_id' => $this->campus->id,
            'code' => $this->campus->code,
            'is_primary' => $this->campus->is_primary,
        ];
    }
}
