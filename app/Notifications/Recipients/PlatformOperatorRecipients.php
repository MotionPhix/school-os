<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Domains\Communications\Events\BroadcastDeliveryFailureDetected;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Events\BusinessEvent;

/**
 * Portal users whose membership roles carry `platform.observability.alert`
 * (platform operators). Resolved per tenant so alerts never leak across
 * tenants.
 */
final class PlatformOperatorRecipients implements ResolvesNotificationRecipients
{
    private const ALERT_KEY = 'platform.observability.alert';

    public function resolve(BusinessEvent $event): iterable
    {
        if (! $event instanceof BroadcastDeliveryFailureDetected) {
            return [];
        }

        $memberships = TenantMembership::query()
            ->where('tenant_id', $event->tenantId)
            ->get(['user_id', 'role_ids']);

        $roleIds = $memberships->flatMap(fn (TenantMembership $membership): array => $membership->role_ids)
            ->unique()
            ->values()
            ->all();

        if ($roleIds === []) {
            return [];
        }

        /** @var list<string> $alertRoleIds */
        $alertRoleIds = Role::query()
            ->whereIn('id', $roleIds)
            ->get(['id', 'permission_keys'])
            ->filter(fn (Role $role): bool => in_array(self::ALERT_KEY, $role->permission_keys, true))
            ->pluck('id')
            ->all();

        if ($alertRoleIds === []) {
            return [];
        }

        $userIds = $memberships
            ->filter(fn (TenantMembership $membership): bool => array_intersect($membership->role_ids, $alertRoleIds) !== [])
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()->whereIn('id', $userIds)->get();
    }
}
