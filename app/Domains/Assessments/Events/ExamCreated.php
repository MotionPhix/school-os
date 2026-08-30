<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\Exam;
use App\Support\Events\BusinessEvent;

final class ExamCreated extends BusinessEvent
{
    public function __construct(public readonly Exam $exam)
    {
        parent::__construct($exam->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.exam.created';
    }

    public function payload(): array
    {
        return [
            'exam_id' => $this->exam->id,
            'period_id' => $this->exam->period_id,
            'course_section_id' => $this->exam->course_section_id,
            'paper_title' => $this->exam->paper_title,
            'scheduled_on' => $this->exam->scheduled_on?->toDateString(),
        ];
    }
}
