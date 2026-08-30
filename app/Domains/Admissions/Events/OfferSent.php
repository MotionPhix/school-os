<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Models\Application;
use App\Models\ApplicationOffer;
use App\Support\Events\BusinessEvent;

final class OfferSent extends BusinessEvent
{
    public function __construct(
        public readonly Application $application,
        public readonly ApplicationOffer $offer,
    ) {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.offer.sent';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'offer_id' => $this->offer->id,
            'fee_amount' => $this->offer->fee_amount,
            'currency_code' => $this->offer->currency_code,
            'expires_on' => $this->offer->expires_on?->toDateString(),
        ];
    }
}
