<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Enums\PipelineStage;
use App\Models\Application;
use App\Models\ApplicationStageEvent;
use App\Support\Events\BusinessEvent;

final class ApplicationStageAdvanced extends BusinessEvent
{
    public function __construct(
        public readonly Application $application,
        public readonly ?PipelineStage $fromStage,
        public readonly PipelineStage $toStage,
        public readonly ApplicationStageEvent $timelineEntry,
    ) {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.application.stage_advanced';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'reference' => $this->application->reference,
            'from_stage' => $this->fromStage?->value,
            'to_stage' => $this->toStage->value,
            'timeline_entry_id' => $this->timelineEntry->id,
        ];
    }
}
