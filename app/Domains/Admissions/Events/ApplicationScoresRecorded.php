<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Models\Application;
use App\Models\User;
use App\Support\Events\BusinessEvent;

final class ApplicationScoresRecorded extends BusinessEvent
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.scores.recorded';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'reference' => $this->application->reference,
            'assessment_score' => $this->application->assessment_score,
            'interview_score' => $this->application->interview_score,
            'actor_id' => $this->actor->id,
        ];
    }
}
