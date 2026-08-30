<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Models\Application;
use App\Support\Events\BusinessEvent;

final class ApplicationCreated extends BusinessEvent
{
    public function __construct(public readonly Application $application)
    {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.application.created';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'reference' => $this->application->reference,
            'campus_id' => $this->application->campus_id,
            'academic_year_id' => $this->application->academic_year_id,
            'intended_grade_label' => $this->application->intended_grade_label,
            'source' => $this->application->source->value,
            'stage' => $this->application->stage->value,
        ];
    }
}
