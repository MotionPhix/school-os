<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\TenantContext;

final class UserPolicy extends AbstractIdentityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'identity.users.read');
    }

    public function view(User $user, User $target): bool
    {
        return $this->inActiveTenant($target) && $this->has($user, 'identity.users.read');
    }

    public function invite(User $user): bool
    {
        return $this->has($user, 'identity.users.write');
    }

    public function suspend(User $user, User $target): bool
    {
        return $user->id !== $target->id
            && $this->inActiveTenant($target)
            && $this->has($user, 'identity.users.write');
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $this->inActiveTenant($target) && $this->has($user, 'identity.users.write');
    }

    /**
     * Users are global records, so every instance-level gate must also
     * confirm the target belongs to the caller's active tenant to prevent
     * cross-tenant IDOR.
     */
    private function inActiveTenant(User $target): bool
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId !== null
            && $target->memberships()->where('tenants.id', $tenantId)->exists();
    }
}
