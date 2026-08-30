<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Models\Application;
use App\Support\Events\BusinessEvent;

final class ApplicationUpdated extends BusinessEvent
{
    public function __construct(public readonly Application $application)
    {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.application.updated';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'reference' => $this->application->reference,
            'stage' => $this->application->stage->value,
        ];
    }
}
