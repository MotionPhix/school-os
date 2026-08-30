<?php

declare(strict_types=1);

namespace App\Policies\Academics;

use App\Models\CourseSection;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class CourseSectionPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'academics.courses.read');
    }

    public function view(User $user, CourseSection $section): bool
    {
        return $this->has($user, 'academics.courses.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'academics.courses.write');
    }

    public function update(User $user, CourseSection $section): bool
    {
        return $this->has($user, 'academics.courses.write');
    }

    public function delete(User $user, CourseSection $section): bool
    {
        return $this->has($user, 'academics.courses.write');
    }

    public function schedule(User $user, CourseSection $section): bool
    {
        return $this->has($user, 'academics.timetable.write');
    }
}
