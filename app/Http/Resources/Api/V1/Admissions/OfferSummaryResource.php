<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admissions;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\ApplicationOffer;
use Illuminate\Http\Request;

/**
 * @mixin ApplicationOffer
 */
final class OfferSummaryResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'fee_amount' => (int) $this->fee_amount,
            'currency_code' => $this->currency_code,
            'sent_at' => $this->iso($this->sent_at),
            'expires_on' => $this->expires_on?->toDateString(),
            'responded_at' => $this->iso($this->responded_at),
        ];
    }
}
