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
     * Platform admins can always create tenants. A user with no memberships
     * at all may create their first one (Day-0 onboarding) — CreateTenant
     * makes them its principal, so this can only ever happen once per user.
     */
    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user)
            || ! $user->memberships()->exists();
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
