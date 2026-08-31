<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformCheckoutResource extends JsonResource
{
    public function __construct(
        private readonly string $checkoutUrl,
        private readonly string $txRef,
    ) {
        parent::__construct(null);
    }

    /** @return array{checkout_url: string, tx_ref: string} */
    public function toArray($request): array
    {
        return [
            'checkout_url' => $this->checkoutUrl,
            'tx_ref' => $this->txRef,
        ];
    }
}
