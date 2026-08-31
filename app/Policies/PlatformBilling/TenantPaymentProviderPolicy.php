<?php

declare(strict_types=1);

namespace App\Policies\PlatformBilling;

use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

class TenantPaymentProviderPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'billing.payments.read');
    }

    public function update(User $user): bool
    {
        return $this->has($user, 'billing.payments.write');
    }
}
