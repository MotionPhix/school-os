<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Reading the activity log requires the same clearance as reading users:
 * it exposes who did what to whom inside the tenant.
 */
final class AuditEventPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'identity.users.read');
    }

    public function view(User $user, AuditEvent $event): bool
    {
        return $this->has($user, 'identity.users.read')
            && $event->tenant_id === app(TenantContext::class)->id();
    }
}
