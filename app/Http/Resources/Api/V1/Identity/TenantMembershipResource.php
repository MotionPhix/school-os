<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\TenantMembership;
use Illuminate\Http\Request;

/**
 * @mixin TenantMembership
 */
final class TenantMembershipResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        // Roles are eager-loaded by the controller via the `role_names_map`
        // key on the resource meta; if absent, we fall back to ids only.
        $roleNamesMap = $this->additional['role_names_map'] ?? [];
        $roleIds = $this->role_ids ?? [];

        return [
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant?->name ?? '',
            'role_ids' => $roleIds,
            'role_names' => array_values(array_filter(
                array_map(fn (string $id): ?string => $roleNamesMap[$id] ?? null, $roleIds),
            )),
            'joined_at' => $this->iso($this->joined_at),
        ];
    }
}
