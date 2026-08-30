<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\CourseSection;
use App\Support\Events\BusinessEvent;

final class CourseSectionUpdated extends BusinessEvent
{
    public function __construct(public readonly CourseSection $section)
    {
        parent::__construct($section->tenant_id);
    }

    public function name(): string
    {
        return 'academics.course_section.updated';
    }

    public function payload(): array
    {
        return [
            'course_section_id' => $this->section->id,
            'status' => $this->section->status->value,
        ];
    }
}
