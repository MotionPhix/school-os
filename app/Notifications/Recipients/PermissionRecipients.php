<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Models\User;
use App\Support\Events\BusinessEvent;

/** Tenant members whose roles carry a capability key. */
final class PermissionRecipients implements ResolvesNotificationRecipients
{
    public function __construct(private readonly string $permissionKey) {}

    public function resolve(BusinessEvent $event): iterable
    {
        return collect((new TenantMembersRecipients)->resolve($event))
            ->filter(fn (User $user): bool => $user->hasPermission($this->permissionKey, $event->tenantId));
    }
}
