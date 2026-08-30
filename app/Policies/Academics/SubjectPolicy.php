<?php

declare(strict_types=1);

namespace App\Policies\Academics;

use App\Models\Subject;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class SubjectPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'academics.subjects.read');
    }

    public function view(User $user, Subject $subject): bool
    {
        return $this->has($user, 'academics.subjects.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'academics.subjects.write');
    }

    public function update(User $user, Subject $subject): bool
    {
        return $this->has($user, 'academics.subjects.write');
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->has($user, 'academics.subjects.write');
    }
}
