<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\ExamPeriod;
use App\Support\Events\BusinessEvent;

final class ExamPeriodCreated extends BusinessEvent
{
    public function __construct(public readonly ExamPeriod $period)
    {
        parent::__construct($period->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.period.created';
    }

    public function payload(): array
    {
        return [
            'period_id' => $this->period->id,
            'term_id' => $this->period->term_id,
            'name' => $this->period->name,
            'starts_on' => $this->period->starts_on?->toDateString(),
            'ends_on' => $this->period->ends_on?->toDateString(),
        ];
    }
}
