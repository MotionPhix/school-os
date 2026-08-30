<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * @mixin User
 */
final class UserResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        // Build a shared id→name map covering every role_id in every
        // membership, one query total (roles are also global-scope free).
        $memberships = $this->memberships;
        $roleIds = collect($memberships)
            ->flatMap(fn ($m) => $m->pivot->role_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $roleNamesMap = $roleIds === []
            ? []
            : Role::query()->whereIn('id', $roleIds)->pluck('name', 'id')->all();

        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->name,
            'avatar_initials' => $this->avatarInitials(),
            'status' => $this->status?->value,
            'last_active_at' => $this->iso($this->last_active_at),
            'mfa_enabled' => $this->mfa_enabled,
            'memberships' => $memberships->map(function ($tenant) use ($roleNamesMap): array {
                /** @var TenantMembership $pivot */
                $pivot = $tenant->pivot;

                return (new TenantMembershipResource($pivot))
                    ->additional(['role_names_map' => $roleNamesMap])
                    ->resolve();
            })->all(),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
