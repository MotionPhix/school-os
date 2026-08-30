<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Campus;
use App\Models\User;

final class CampusPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'institution.campuses.read');
    }

    public function view(User $user, Campus $campus): bool
    {
        return $this->has($user, 'institution.campuses.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'institution.campuses.write');
    }

    public function update(User $user, Campus $campus): bool
    {
        return $this->has($user, 'institution.campuses.write');
    }

    public function delete(User $user, Campus $campus): bool
    {
        return $this->has($user, 'institution.campuses.write') && ! $campus->is_primary;
    }
}
