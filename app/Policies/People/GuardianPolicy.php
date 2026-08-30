<?php

declare(strict_types=1);

namespace App\Policies\People;

use App\Models\Guardian;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class GuardianPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'people.guardians.read');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->has($user, 'people.guardians.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'people.guardians.write');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->has($user, 'people.guardians.write');
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $this->has($user, 'people.guardians.write');
    }
}
