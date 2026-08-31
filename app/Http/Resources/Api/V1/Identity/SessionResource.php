<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Role;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * Snapshot of the authenticated session as the Presentation Contract
 * `Session` shape expects. Constructed inline by SessionController.
 *
 * @property User $user_model
 * @property string $active_tenant_id
 */
final class SessionResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        /** @var array{user:User, active_tenant_id:string, issued_at:DateTimeInterface} $r */
        $r = (array) $this->resource;

        $user = $r['user'];
        $activeTenantId = $r['active_tenant_id'];

        $membership = $user->memberships->firstWhere('id', $activeTenantId)?->pivot;
        $activeRoleIds = $membership?->role_ids ?? [];

        $activeRoles = Role::query()
            ->whereIn('id', $activeRoleIds)
            ->get();

        $roleKeys = $activeRoles->pluck('key')->values()->all();
        $permissionKeys = $activeRoles
            ->flatMap(fn (Role $r) => $r->permission_keys)
            ->unique()
            ->values()
            ->all();

        return [
            'user' => (new UserResource($user))->resolve($request),
            // Landing contract: lets the SPA route deterministically —
            // unverified → verify screen (also signalled by the 403 from
            // EnsureEmailVerified), verified without memberships → onboarding,
            // with memberships → console (invited members land directly).
            'email_verified' => $user->hasVerifiedEmail(),
            'has_memberships' => $user->memberships->isNotEmpty(),
            'active_tenant_id' => $activeTenantId,
            'active_role_keys' => $roleKeys,
            'effective_permission_keys' => $permissionKeys,
            'issued_at' => $r['issued_at']->format(DateTimeInterface::ATOM),
        ];
    }
}
