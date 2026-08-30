<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Support\Events\BusinessEvent;

final class ExamStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly Exam $exam,
        public readonly ExamStatus $from,
        public readonly ExamStatus $to,
    ) {
        parent::__construct($exam->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.exam.status_changed';
    }

    public function payload(): array
    {
        return [
            'exam_id' => $this->exam->id,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
