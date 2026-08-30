<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Domains\Admissions\Events\OfferResponded;
use App\Enums\OfferStatus;
use App\Enums\PipelineStage;
use App\Models\Application;
use App\Models\ApplicationStageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Record an applicant's response to the currently live offer. Accepted
 * moves the application to Accepted; Declined moves it to Withdrawn.
 * The timeline actor is the authenticated user performing the action
 * (registrar, or the guardian through the portal) — never a
 * client-supplied name, so the audit trail cannot be spoofed.
 */
final class RespondToOffer
{
    public function handle(Application $application, OfferStatus $response, ?User $actor = null): Application
    {
        if (! in_array($response, [OfferStatus::Accepted, OfferStatus::Declined], true)) {
            throw new HttpException(422, "Offer response must be 'accepted' or 'declined'.");
        }

        return DB::transaction(function () use ($application, $response, $actor): Application {
            $offer = $application->offers()->latest('created_at')->first();
            if ($offer === null) {
                throw new HttpException(422, 'No offer exists for this application.');
            }
            if (! in_array($offer->status, [OfferStatus::Draft, OfferStatus::Sent], true)) {
                throw new HttpException(422, "Offer is '{$offer->status->value}' and cannot be responded to.");
            }

            $offer->status = $response;
            $offer->responded_at = now();
            $offer->save();

            $from = $application->stage;
            $to = $response === OfferStatus::Accepted
                ? PipelineStage::Accepted
                : PipelineStage::Withdrawn;

            $application->stage = $to;
            $application->save();
            $application->refresh();

            ApplicationStageEvent::create([
                'tenant_id' => $application->tenant_id,
                'application_id' => $application->id,
                'from_stage' => $from->value,
                'to_stage' => $to->value,
                'note' => "Offer {$response->value}",
                'actor_name' => $actor?->name ?? $application->guardian_name,
                'actor_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            OfferResponded::dispatch($application, $offer->fresh(), $response);

            return $application;
        });
    }
}
