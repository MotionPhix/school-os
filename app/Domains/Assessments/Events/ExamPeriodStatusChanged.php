<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Enums\ExamPeriodStatus;
use App\Models\ExamPeriod;
use App\Support\Events\BusinessEvent;

final class ExamPeriodStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly ExamPeriod $period,
        public readonly ExamPeriodStatus $from,
        public readonly ExamPeriodStatus $to,
    ) {
        parent::__construct($period->tenant_id);
    }

    public function name(): string
    {
        return 'assessments.period.status_changed';
    }

    public function payload(): array
    {
        return [
            'period_id' => $this->period->id,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
