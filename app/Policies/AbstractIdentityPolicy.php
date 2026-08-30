<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;

/**
 * All Identity policies gate on the effective permission keys of the
 * caller's active tenant membership. `resolveKeys()` computes them
 * once per request.
 */
abstract class AbstractIdentityPolicy
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
