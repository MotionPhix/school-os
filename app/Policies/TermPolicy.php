<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TermStatus;
use App\Models\Term;
use App\Models\User;

final class TermPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'institution.years.read');
    }

    public function view(User $user, Term $term): bool
    {
        return $this->has($user, 'institution.years.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'institution.years.write');
    }

    public function update(User $user, Term $term): bool
    {
        return $this->has($user, 'institution.years.write');
    }

    /**
     * A term that is in progress is part of the live academic record and
     * may not be deleted.
     */
    public function delete(User $user, Term $term): bool
    {
        return $this->has($user, 'institution.years.write')
            && $term->status !== TermStatus::InProgress;
    }
}
