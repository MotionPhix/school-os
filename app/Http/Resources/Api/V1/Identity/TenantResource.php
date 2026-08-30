<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Campus;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * @mixin Tenant
 */
final class TenantResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'currency_code' => $this->currency_code,
            'tier' => $this->tier->value,
            'status' => $this->status->value,
            'created_at' => $this->iso($this->created_at),
            'member_count' => (int) ($this->users_count ?? $this->users()->count()),
            'campus_count' => (int) ($this->campuses_count ?? Campus::query()->withoutGlobalScopes()->where('tenant_id', $this->id)->count()),
        ];
    }
}
