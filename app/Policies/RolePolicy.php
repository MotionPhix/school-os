<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;

final class RolePolicy extends AbstractIdentityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'identity.roles.read');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->inScope($role) && $this->has($user, 'identity.roles.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'identity.roles.write');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->ownedByActiveTenant($role)
            && ! $role->is_system
            && $this->has($user, 'identity.roles.write');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->ownedByActiveTenant($role)
            && ! $role->is_system
            && $this->has($user, 'identity.roles.write');
    }

    private function activeTenantId(): ?string
    {
        return app(TenantContext::class)->id();
    }

    /** Platform roles (tenant_id null) are visible to every tenant. */
    private function inScope(Role $role): bool
    {
        return $role->tenant_id === null || $role->tenant_id === $this->activeTenantId();
    }

    /** Only the owning tenant may modify a role — prevents cross-tenant IDOR. */
    private function ownedByActiveTenant(Role $role): bool
    {
        $tenantId = $this->activeTenantId();

        return $tenantId !== null && $role->tenant_id === $tenantId;
    }
}
