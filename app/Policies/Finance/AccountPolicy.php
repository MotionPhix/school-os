<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Account;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class AccountPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'finance.ledger.read');
    }

    public function view(User $user, Account $account): bool
    {
        return $this->has($user, 'finance.ledger.read');
    }
}
