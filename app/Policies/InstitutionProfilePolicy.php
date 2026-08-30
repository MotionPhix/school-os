<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InstitutionProfile;
use App\Models\User;

final class InstitutionProfilePolicy extends AbstractCapabilityPolicy
{
    public function view(User $user, ?InstitutionProfile $profile = null): bool
    {
        return $this->has($user, 'institution.profile.read');
    }

    public function update(User $user, ?InstitutionProfile $profile = null): bool
    {
        return $this->has($user, 'institution.profile.write');
    }
}
