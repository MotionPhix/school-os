<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Events;

use App\Enums\OfferStatus;
use App\Models\Application;
use App\Models\ApplicationOffer;
use App\Support\Events\BusinessEvent;

final class OfferResponded extends BusinessEvent
{
    public function __construct(
        public readonly Application $application,
        public readonly ApplicationOffer $offer,
        public readonly OfferStatus $response,
    ) {
        parent::__construct($application->tenant_id);
    }

    public function name(): string
    {
        return 'admissions.offer.responded';
    }

    public function payload(): array
    {
        return [
            'application_id' => $this->application->id,
            'offer_id' => $this->offer->id,
            'response' => $this->response->value,
            'responded_at' => $this->offer->responded_at?->toIso8601String(),
        ];
    }
}
