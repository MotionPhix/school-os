<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Models\User;
use App\Support\Events\BusinessEvent;

/** Every user with a membership in the event's tenant. */
final class TenantMembersRecipients implements ResolvesNotificationRecipients
{
    public function resolve(BusinessEvent $event): iterable
    {
        return User::query()
            ->whereHas('memberships', fn ($query) => $query->where('tenants.id', $event->tenantId))
            ->get();
    }
}
