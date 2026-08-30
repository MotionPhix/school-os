<?php

declare(strict_types=1);

namespace App\Policies\Attendance;

use App\Models\AttendanceMark;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class AttendanceMarkPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'attendance.sessions.read');
    }

    public function view(User $user, AttendanceMark $mark): bool
    {
        return $this->has($user, 'attendance.sessions.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'attendance.marks.write');
    }

    public function update(User $user, AttendanceMark $mark): bool
    {
        return $this->has($user, 'attendance.marks.write');
    }
}
