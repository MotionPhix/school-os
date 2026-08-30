<?php

declare(strict_types=1);

namespace App\Domains\Communications\Support;

use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Small helper that answers "does the caller hold this permission key
 * within the active tenant?". Mirrors AbstractCapabilityPolicy::has()
 * so gate-style checks (that aren't tied to a model) can reuse the same
 * tenant→memberships→roles→keys resolution.
 */
final class CommunicationsPermission
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
