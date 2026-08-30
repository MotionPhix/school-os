<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class TrashPolicy extends AbstractCapabilityPolicy
{
    public function restore(User $user): bool
    {
        return $this->has($user, 'platform.trash.restore');
    }
}
