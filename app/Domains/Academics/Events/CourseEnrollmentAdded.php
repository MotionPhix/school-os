<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\CourseSection;
use App\Models\Student;
use App\Support\Events\BusinessEvent;

final class CourseEnrollmentAdded extends BusinessEvent
{
    public function __construct(
        public readonly CourseSection $section,
        public readonly Student $student,
    ) {
        parent::__construct($section->tenant_id);
    }

    public function name(): string
    {
        return 'academics.course_enrollment.added';
    }

    public function payload(): array
    {
        return [
            'course_section_id' => $this->section->id,
            'student_id' => $this->student->id,
        ];
    }
}
