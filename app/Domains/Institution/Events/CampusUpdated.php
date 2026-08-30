<?php

declare(strict_types=1);

namespace App\Domains\Institution\Events;

use App\Models\Campus;
use App\Support\Events\BusinessEvent;

final class CampusUpdated extends BusinessEvent
{
    public function __construct(public readonly Campus $campus)
    {
        parent::__construct($campus->tenant_id);
    }

    public function name(): string
    {
        return 'institution.campus.updated';
    }

    public function payload(): array
    {
        return [
            'campus_id' => $this->campus->id,
            'status' => $this->campus->status->value,
        ];
    }
}
