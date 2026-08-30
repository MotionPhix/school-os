<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Domains\Admissions\Events\ApplicationScoresRecorded;
use App\Models\Application;
use App\Models\ApplicationStageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Record assessment and/or interview scores against an open application.
 *
 * Scores are a precondition for the Offer stage (see StageTransitionGuard),
 * so writing them appends a same-stage timeline entry for auditability.
 * Keys absent from the payload are left untouched; explicit nulls clear.
 */
final class RecordAssessmentScores
{
    /**
     * @param  array{assessment_score?:int|null, interview_score?:int|null}  $payload
     */
    public function handle(Application $application, array $payload, User $actor): Application
    {
        if ($application->stage->isTerminal()) {
            throw new HttpException(422, 'This application is closed — scores can no longer change.');
        }

        return DB::transaction(function () use ($application, $payload, $actor): Application {
            $notes = [];

            foreach (['assessment_score', 'interview_score'] as $field) {
                if (! array_key_exists($field, $payload)) {
                    continue;
                }

                $value = $payload[$field];
                $application->{$field} = $value;
                $notes[] = str_replace('_score', '', $field).' '.($value ?? 'cleared');
            }

            if ($notes === []) {
                return $application;
            }

            $application->save();
            $application->refresh();

            ApplicationStageEvent::create([
                'tenant_id' => $application->tenant_id,
                'application_id' => $application->id,
                'from_stage' => $application->stage->value,
                'to_stage' => $application->stage->value,
                'note' => 'Scores recorded — '.implode(', ', $notes),
                'actor_name' => $actor->name,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            ApplicationScoresRecorded::dispatch($application, $actor);

            return $application;
        });
    }
}
