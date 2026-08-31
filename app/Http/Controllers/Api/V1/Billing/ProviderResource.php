<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Models\TenantPaymentProvider;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        if (! $this->resource instanceof TenantPaymentProvider) {
            return ['provider' => null, 'is_active' => false];
        }

        /** @var TenantPaymentProvider $provider */
        $provider = $this->resource;

        return [
            'provider' => $provider->provider,
            'mode' => $provider->mode,
            'is_active' => $provider->is_active,
            'has_secret_key' => $provider->secret_key !== null,
            'verified_at' => $provider->verified_at?->toISOString(),
        ];
    }
}
