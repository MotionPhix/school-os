<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Models\Application;
use App\Models\Student;
use App\Support\Events\BusinessEvent;

final class ApplicationEnrolled extends BusinessEvent
{
    public function __construct(
        public readonly Application $application,
        public readonly Student $student,
    ) {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.application.enrolled';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'reference' => $this->application->reference,
            'student_id' => $this->student->id,
            'admission_number' => $this->student->admission_number,
        ];
    }
}
