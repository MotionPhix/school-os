<?php

declare(strict_types=1);

namespace App\Policies\PlatformBilling;

use App\Models\PlatformInvoice;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

class PlatformInvoicePolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'billing.payments.read');
    }

    public function view(User $user, PlatformInvoice $invoice): bool
    {
        return $this->viewAny($user);
    }

    public function checkout(User $user, PlatformInvoice $invoice): bool
    {
        return $this->has($user, 'billing.payments.write');
    }
}
