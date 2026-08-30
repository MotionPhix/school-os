<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\GradebookEntry;
use App\Support\Events\BusinessEvent;

final class GradebookEntryRecorded extends BusinessEvent
{
    public function __construct(public readonly GradebookEntry $entry)
    {
        parent::__construct($entry->tenant_id);
    }

    public function name(): string
    {
        return 'academics.gradebook_entry.recorded';
    }

    public function payload(): array
    {
        return [
            'gradebook_entry_id' => $this->entry->id,
            'course_section_id' => $this->entry->course_section_id,
            'term_id' => $this->entry->term_id,
            'student_id' => $this->entry->student_id,
            'continuous_assessment' => $this->entry->continuous_assessment,
            'exam_score' => $this->entry->exam_score,
            'total' => $this->entry->total,
            'band' => $this->entry->band->value,
        ];
    }
}
