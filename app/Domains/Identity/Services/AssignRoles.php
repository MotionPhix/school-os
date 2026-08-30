<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\RolesAssigned;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AssignRoles
{
    /**
     * Replace the role_ids on a user's membership in a tenant.
     *
     * @param  list<string>  $roleIds
     */
    public function handle(User $user, string $tenantId, array $roleIds, string $actorId): TenantMembership
    {
        $membership = TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($membership === null) {
            throw new HttpException(404, 'User is not a member of this tenant.');
        }

        // Only accept role ids that actually exist and are visible to
        // this tenant (own or platform).
        $validIds = Role::query()
            ->whereIn('id', $roleIds)
            ->where(function ($q) use ($tenantId): void {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id')
            ->all();

        $membership->update(['role_ids' => array_values($validIds)]);

        RolesAssigned::dispatch($tenantId, $user->id, $validIds, $actorId);

        return $membership;
    }
}
