<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Models\PlatformPayment;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        /** @var PlatformPayment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'tx_ref' => $payment->tx_ref,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'checkout_url' => $payment->checkout_url,
            'verified_at' => $payment->verified_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }
}
