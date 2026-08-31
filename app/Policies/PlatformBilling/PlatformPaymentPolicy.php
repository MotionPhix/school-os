<?php

declare(strict_types=1);

namespace App\Policies\PlatformBilling;

use App\Models\PlatformPayment;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

class PlatformPaymentPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'billing.payments.read');
    }

    public function view(User $user, PlatformPayment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function refresh(User $user, PlatformPayment $payment): bool
    {
        return $this->viewAny($user);
    }
}
