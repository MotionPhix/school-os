<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Domains\Admissions\Events\ApplicationStageAdvanced;
use App\Enums\PipelineStage;
use App\Models\Application;
use App\Models\ApplicationStageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Move an application to a new pipeline stage and append a timeline
 * entry. Refuses transitions out of terminal stages (Enrolled, Rejected,
 * Withdrawn) — those are one-way.
 */
final class AdvanceApplicationStage
{
    public function __construct(
        private readonly StageTransitionGuard $guard,
    ) {}

    public function handle(
        Application $application,
        PipelineStage $to,
        ?string $note,
        User $actor,
    ): Application {
        if ($application->stage === $to) {
            return $application;
        }

        // Every guard lives in StageTransitionGuard so the API and the
        // frontend optimistic UI refuse exactly the same moves.
        $this->guard->assert($application, $to);

        return DB::transaction(function () use ($application, $to, $note, $actor): Application {
            $from = $application->stage;
            $application->stage = $to;
            $application->save();
            $application->refresh();

            $entry = ApplicationStageEvent::create([
                'tenant_id' => $application->tenant_id,
                'application_id' => $application->id,
                'from_stage' => $from->value,
                'to_stage' => $to->value,
                'note' => $note,
                'actor_name' => $actor->name,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            ApplicationStageAdvanced::dispatch($application, $from, $to, $entry);

            return $application;
        });
    }
}
