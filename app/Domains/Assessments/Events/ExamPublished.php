<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\Exam;
use App\Support\Events\BusinessEvent;

/**
 * Dispatched when an exam flips to `published`. This is the hook for
 * report-card recomputation and (Slice 9) guardian notifications.
 */
final class ExamPublished extends BusinessEvent
{
    public function __construct(public readonly Exam $exam)
    {
        parent::__construct($exam->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.exam.published';
    }

    public function payload(): array
    {
        return [
            'exam_id' => $this->exam->id,
            'period_id' => $this->exam->period_id,
            'course_section_id' => $this->exam->course_section_id,
            'result_count' => $this->exam->results()->graded()->count(),
        ];
    }
}
