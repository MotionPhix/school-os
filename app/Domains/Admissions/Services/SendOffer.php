<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Domains\Admissions\Events\OfferSent;
use App\Enums\OfferStatus;
use App\Enums\PipelineStage;
use App\Models\Application;
use App\Models\ApplicationOffer;
use App\Models\ApplicationStageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Draft and send an offer to an applicant. Any prior non-terminal offer
 * on the same application is marked Expired so there is at most one
 * live offer at any time. Advances the application to the Offer stage.
 *
 * TODO(Slice 9 Communications): dispatch guardian email/SMS off OfferSent.
 */
final class SendOffer
{
    public function __construct(
        private readonly StageTransitionGuard $guard,
    ) {}

    /**
     * @param  array{fee_amount:int, currency_code:string, expires_on?:string|null}  $payload
     */
    public function handle(Application $application, array $payload, User $actor): Application
    {
        if ($application->stage !== PipelineStage::Offer) {
            $this->guard->assert($application, PipelineStage::Offer);
        }

        $live = $application->currentOffer;
        if ($live && in_array($live->status, [OfferStatus::Accepted, OfferStatus::Declined], true)) {
            throw new HttpException(422, 'The guardian already responded to this offer.');
        }

        if ((int) $payload['fee_amount'] <= 0) {
            throw new HttpException(422, 'Offer amount must be greater than zero.');
        }

        if (! empty($payload['expires_on']) && $payload['expires_on'] <= now()->toDateString()) {
            throw new HttpException(422, 'The offer expiry must be a future date.');
        }

        return DB::transaction(function () use ($application, $payload, $actor): Application {
            ApplicationOffer::query()
                ->where('application_id', $application->id)
                ->whereIn('status', [OfferStatus::Draft->value, OfferStatus::Sent->value])
                ->update(['status' => OfferStatus::Expired->value]);

            $offer = ApplicationOffer::create([
                'tenant_id' => $application->tenant_id,
                'application_id' => $application->id,
                'status' => OfferStatus::Sent,
                'fee_amount' => (int) $payload['fee_amount'],
                'currency_code' => mb_strtoupper((string) $payload['currency_code']),
                'sent_at' => now(),
                'expires_on' => $payload['expires_on']
                    ?? now()->addDays((int) config('admissions.offer.default_ttl_days', 21))->toDateString(),
            ]);

            $from = $application->stage;
            $application->stage = PipelineStage::Offer;
            $application->save();
            $application->refresh();

            ApplicationStageEvent::create([
                'tenant_id' => $application->tenant_id,
                'application_id' => $application->id,
                'from_stage' => $from->value,
                'to_stage' => PipelineStage::Offer->value,
                'note' => sprintf(
                    'Offer sent — %s %s',
                    $offer->currency_code,
                    number_format($offer->fee_amount / 100, 2),
                ),
                'actor_name' => $actor->name,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            OfferSent::dispatch($application, $offer);

            return $application;
        });
    }
}
