<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\User;

final class AcademicYearPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'institution.years.read');
    }

    public function view(User $user, AcademicYear $year): bool
    {
        return $this->has($user, 'institution.years.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'institution.years.write');
    }

    public function update(User $user, AcademicYear $year): bool
    {
        return $this->has($user, 'institution.years.write');
    }

    public function setCurrent(User $user, AcademicYear $year): bool
    {
        return $this->has($user, 'institution.years.write');
    }

    /**
     * A year that has run (active or closed) is part of the academic record
     * and may never be deleted — only planning years can be discarded.
     */
    public function delete(User $user, AcademicYear $year): bool
    {
        return $this->has($user, 'institution.years.write')
            && $year->status === AcademicYearStatus::Planning
            && ! $year->is_current;
    }
}
