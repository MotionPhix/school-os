<?php

declare(strict_types=1);

namespace App\Policies\Assessments;

use App\Models\ExamResult;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class ExamResultPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'assessments.exams.read');
    }

    public function view(User $user, ExamResult $result): bool
    {
        return $this->has($user, 'assessments.exams.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'assessments.results.write');
    }

    public function update(User $user, ExamResult $result): bool
    {
        return $this->has($user, 'assessments.results.write');
    }
}
