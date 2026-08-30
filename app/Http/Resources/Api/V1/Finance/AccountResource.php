<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Finance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Account;
use Illuminate\Http\Request;

/**
 * @mixin Account
 */
final class AccountResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'type' => $this->type->value,
            'name' => $this->name,
            'currency' => $this->currency->value,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
