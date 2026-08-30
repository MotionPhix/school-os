<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\CourseSection;
use App\Support\Events\BusinessEvent;

final class CourseSectionCreated extends BusinessEvent
{
    public function __construct(public readonly CourseSection $section)
    {
        parent::__construct($section->tenant_id);
    }

    public function name(): string
    {
        return 'academics.course_section.created';
    }

    public function payload(): array
    {
        return [
            'course_section_id' => $this->section->id,
            'academic_year_id' => $this->section->academic_year_id,
            'campus_id' => $this->section->campus_id,
            'subject_id' => $this->section->subject_id,
            'teacher_id' => $this->section->teacher_id,
            'grade_label' => $this->section->grade_label,
            'section_label' => $this->section->section_label,
            'status' => $this->section->status->value,
        ];
    }
}
