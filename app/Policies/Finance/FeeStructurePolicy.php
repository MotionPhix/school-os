<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\FeeStructure;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class FeeStructurePolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'finance.fees.read');
    }

    public function view(User $user, FeeStructure $fee): bool
    {
        return $this->has($user, 'finance.fees.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'finance.fees.write');
    }

    public function update(User $user, FeeStructure $fee): bool
    {
        return $this->has($user, 'finance.fees.write');
    }

    public function delete(User $user, FeeStructure $fee): bool
    {
        return $this->has($user, 'finance.fees.write');
    }
}
