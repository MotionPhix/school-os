<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

final class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // controller scopes to memberships or platform-wide
    }

    /** Only platform admins may list tenants they are not a member of. */
    public function viewAll(User $user): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->isPlatformAdmin($user)
            || $user->memberships()->where('tenants.id', $tenant->id)->exists();
    }

    /**
     * Platform admins can always create tenants. Any other user may create a
     * tenant while their membership count is below the per-account cap
     * (`identity.max_tenants_per_user`, default 5) — Day-0 onboarding is the
     * first of those; multi-tenant owners stay under the same cap, and
     * CreateTenant re-checks it inside the transaction (defense in depth).
     */
    public function create(User $user): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $max = config('identity.max_tenants_per_user');
        $max = is_int($max) ? max(1, $max) : 5;

        return $user->memberships()->count() < $max;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->isPlatformAdmin($user);
    }

    /**
     * True if the user holds the seeded platform-admin role in any of
     * their memberships. Platform admin can act across tenant boundaries.
     */
    private function isPlatformAdmin(User $user): bool
    {
        $platformAdminId = Role::query()
            ->whereNull('tenant_id')
            ->where('key', 'platform.admin')
            ->value('id');

        if ($platformAdminId === null) {
            return false;
        }

        return TenantMembership::query()
            ->where('user_id', $user->id)
            ->get(['role_ids'])
            ->contains(fn ($m) => in_array($platformAdminId, $m->role_ids ?? [], true));
    }
}
