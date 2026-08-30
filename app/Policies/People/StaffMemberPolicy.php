<?php

declare(strict_types=1);

namespace App\Policies\People;

use App\Models\StaffMember;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class StaffMemberPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'people.staff.read');
    }

    public function view(User $user, StaffMember $staff): bool
    {
        return $this->has($user, 'people.staff.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'people.staff.write');
    }

    public function update(User $user, StaffMember $staff): bool
    {
        return $this->has($user, 'people.staff.write');
    }

    public function delete(User $user, StaffMember $staff): bool
    {
        return $this->has($user, 'people.staff.write');
    }
}
