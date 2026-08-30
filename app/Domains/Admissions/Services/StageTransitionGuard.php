<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Enums\OfferStatus;
use App\Enums\PipelineStage;
use App\Models\Application;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Single source of truth for "can this application move to that stage?".
 *
 * Mirrors transitionBlockReason() in the frontend admissions verbs so the
 * optimistic UI and the API agree on every guard. Returns null when the
 * move is legal, otherwise a human-readable reason.
 */
final class StageTransitionGuard
{
    public function reason(Application $application, PipelineStage $to): ?string
    {
        $from = $application->stage;

        if ($from === $to) {
            return "Already at {$to->label()}.";
        }

        if ($from->isTerminal()) {
            return "Application is closed ({$from->label()}) and cannot be moved.";
        }

        if (! $from->canMoveTo($to)) {
            return "Cannot move from {$from->label()} to {$to->label()}.";
        }

        if ($to === PipelineStage::Offer && $application->assessment_score === null) {
            return 'Record an assessment score before making an offer.';
        }

        if ($to === PipelineStage::Accepted) {
            $offer = $application->currentOffer;
            if (! $offer || $offer->status !== OfferStatus::Accepted) {
                return 'The guardian must accept the offer first.';
            }
        }

        if ($to === PipelineStage::Enrolled && $from !== PipelineStage::Accepted) {
            return 'Only an accepted applicant can be enrolled.';
        }

        return null;
    }

    public function assert(Application $application, PipelineStage $to): void
    {
        $reason = $this->reason($application, $to);

        if ($reason !== null) {
            throw new HttpException(422, $reason);
        }
    }
}
