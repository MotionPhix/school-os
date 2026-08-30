<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class PaymentPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'finance.payments.read');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->has($user, 'finance.payments.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'finance.payments.write');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->has($user, 'finance.payments.refund') && $payment->status === PaymentStatus::Succeeded;
    }
}
