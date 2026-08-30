<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\Exam;
use App\Support\Events\BusinessEvent;

final class ExamUpdated extends BusinessEvent
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(public readonly Exam $exam, public readonly array $changes = [])
    {
        parent::__construct($exam->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.exam.updated';
    }

    public function payload(): array
    {
        return [
            'exam_id' => $this->exam->id,
            'changes' => $this->changes,
        ];
    }
}
