<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Enums\CourseStatus;
use App\Models\CourseSection;
use App\Support\Events\BusinessEvent;

final class CourseSectionStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly CourseSection $section,
        public readonly CourseStatus $previous,
    ) {
        parent::__construct($section->tenant_id);
    }

    public function name(): string
    {
        return 'academics.course_section.status_changed';
    }

    public function payload(): array
    {
        return [
            'course_section_id' => $this->section->id,
            'from' => $this->previous->value,
            'to' => $this->section->status->value,
        ];
    }
}
