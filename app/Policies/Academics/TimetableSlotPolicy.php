<?php

declare(strict_types=1);

namespace App\Policies\Academics;

use App\Models\TimetableSlot;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class TimetableSlotPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'academics.timetable.read');
    }

    public function view(User $user, TimetableSlot $slot): bool
    {
        return $this->has($user, 'academics.timetable.read');
    }

    public function delete(User $user, TimetableSlot $slot): bool
    {
        return $this->has($user, 'academics.timetable.write');
    }
}
