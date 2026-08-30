<?php

declare(strict_types=1);

namespace App\Domains\Insights\Support;

use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Gate-style permission check for insights endpoints. Mirrors the
 * pattern used in CommunicationsPermission so read-only dashboards
 * don't need per-model policies.
 */
final class InsightsPermission
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function has(User $user, string $key): bool
    {
        $tenantId = $this->tenant->id();
        if ($tenantId === null) {
            return false;
        }

        $membership = $user->memberships()
            ->where('tenants.id', $tenantId)
            ->first()?->pivot;

        $roleIds = $membership?->role_ids ?? [];
        if ($roleIds === []) {
            return false;
        }

        $keys = Role::query()
            ->whereIn('id', $roleIds)
            ->get(['permission_keys'])
            ->flatMap(fn ($r) => $r->permission_keys)
            ->unique();

        return $keys->contains($key);
    }
}
