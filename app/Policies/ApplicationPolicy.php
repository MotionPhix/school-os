<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

final class ApplicationPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'admissions.applications.read');
    }

    public function view(User $user, Application $application): bool
    {
        return $this->has($user, 'admissions.applications.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'admissions.applications.write');
    }

    public function update(User $user, Application $application): bool
    {
        return $this->has($user, 'admissions.applications.write');
    }

    public function delete(User $user, Application $application): bool
    {
        return $this->has($user, 'admissions.applications.write')
            && $application->stage->isOpen();
    }

    public function sendOffer(User $user, Application $application): bool
    {
        return $this->has($user, 'admissions.offers.write');
    }

    public function respondOffer(User $user, Application $application): bool
    {
        // Recording a guardian's response is a registrar/write action.
        return $this->has($user, 'admissions.offers.write');
    }

    public function enroll(User $user, Application $application): bool
    {
        return $this->has($user, 'admissions.enroll');
    }
}
