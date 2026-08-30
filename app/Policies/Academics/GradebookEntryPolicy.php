<?php

declare(strict_types=1);

namespace App\Policies\Academics;

use App\Models\GradebookEntry;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class GradebookEntryPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'academics.gradebook.read');
    }

    public function view(User $user, GradebookEntry $entry): bool
    {
        return $this->has($user, 'academics.gradebook.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'academics.gradebook.write');
    }

    public function update(User $user, GradebookEntry $entry): bool
    {
        return $this->has($user, 'academics.gradebook.write');
    }

    public function delete(User $user, GradebookEntry $entry): bool
    {
        return $this->has($user, 'academics.gradebook.write');
    }
}
