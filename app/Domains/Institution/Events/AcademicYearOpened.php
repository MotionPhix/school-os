<?php

declare(strict_types=1);

namespace App\Domains\Institution\Events;

use App\Models\AcademicYear;
use App\Support\Events\BusinessEvent;

final class AcademicYearOpened extends BusinessEvent
{
    public function __construct(public readonly AcademicYear $year)
    {
        parent::__construct($year->tenant_id);
    }

    public function name(): string
    {
        return 'institution.academic_year.opened';
    }

    public function payload(): array
    {
        return [
            'academic_year_id' => $this->year->id,
            'label' => $this->year->label,
        ];
    }
}
