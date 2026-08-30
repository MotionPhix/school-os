<?php

declare(strict_types=1);

namespace App\Policies\Attendance;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class AttendanceSessionPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'attendance.sessions.read');
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        return $this->has($user, 'attendance.sessions.read');
    }

    /** The per-student rollup is a dedicated read — see config/attendance.php. */
    public function viewSummary(User $user): bool
    {
        return $this->has($user, 'attendance.summary.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'attendance.sessions.write');
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        return $this->has($user, 'attendance.sessions.write');
    }

    /** Submitting a register locks it — same permission as opening. */
    public function submit(User $user, AttendanceSession $session): bool
    {
        return $this->has($user, 'attendance.sessions.write');
    }

    /** Reopening a submitted register is a correction — same permission as submitting. */
    public function reopen(User $user, AttendanceSession $session): bool
    {
        return $this->has($user, 'attendance.sessions.write');
    }

    public function delete(User $user, AttendanceSession $session): bool
    {
        // Only draft sessions can be deleted.
        return $this->has($user, 'attendance.sessions.write')
            && ! $session->status->isLocked();
    }
}
