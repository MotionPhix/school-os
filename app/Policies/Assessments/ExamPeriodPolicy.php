<?php

declare(strict_types=1);

namespace App\Policies\Assessments;

use App\Enums\ExamPeriodStatus;
use App\Models\ExamPeriod;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class ExamPeriodPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'assessments.periods.read');
    }

    public function view(User $user, ExamPeriod $period): bool
    {
        return $this->has($user, 'assessments.periods.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'assessments.periods.write');
    }

    public function update(User $user, ExamPeriod $period): bool
    {
        return $this->has($user, 'assessments.periods.write');
    }

    public function delete(User $user, ExamPeriod $period): bool
    {
        return $this->has($user, 'assessments.periods.write')
            && $period->status === ExamPeriodStatus::Draft
            && $period->exams()->count() === 0;
    }
}
