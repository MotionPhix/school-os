<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Shared base for capability policies outside Identity.
 *
 * Mirrors AbstractIdentityPolicy but lives at the policy layer so
 * every capability (Institution, People, Academics, …) resolves the
 * caller's effective permission keys from the active tenant membership
 * the same way, in one place.
 */
abstract class AbstractCapabilityPolicy
{
    /** @return list<string> */
    protected function keys(User $user): array
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            return [];
        }

        $membership = $user->memberships()
            ->where('tenants.id', $tenantId)
            ->first()?->pivot;

        $roleIds = $membership?->role_ids ?? [];
        if ($roleIds === []) {
            return [];
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->get(['permission_keys'])
            ->flatMap(fn ($r) => $r->permission_keys)
            ->unique()
            ->values()
            ->all();
    }

    protected function has(User $user, string $key): bool
    {
        return in_array($key, $this->keys($user), true);
    }
}
