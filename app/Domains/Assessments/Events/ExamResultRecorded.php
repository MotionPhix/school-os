<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\ExamResult;
use App\Support\Events\BusinessEvent;

final class ExamResultRecorded extends BusinessEvent
{
    public function __construct(public readonly ExamResult $result)
    {
        parent::__construct($result->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.result.recorded';
    }

    public function payload(): array
    {
        return [
            'result_id' => $this->result->id,
            'exam_id' => $this->result->exam_id,
            'student_id' => $this->result->student_id,
            'score' => $this->result->score,
            'band' => $this->result->band?->value,
        ];
    }
}
