<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

final class CalendarEventPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'institution.calendar.read');
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        return $this->has($user, 'institution.calendar.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'institution.calendar.write');
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $this->has($user, 'institution.calendar.write');
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->has($user, 'institution.calendar.write');
    }
}
