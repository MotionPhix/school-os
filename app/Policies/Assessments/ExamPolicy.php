<?php

declare(strict_types=1);

namespace App\Policies\Assessments;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class ExamPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'assessments.exams.read');
    }

    public function view(User $user, Exam $exam): bool
    {
        return $this->has($user, 'assessments.exams.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'assessments.exams.write');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $this->has($user, 'assessments.exams.write')
            && ! $exam->status->isLocked();
    }

    /** Publishing is a dedicated permission — one hop above regular writes. */
    public function publish(User $user, Exam $exam): bool
    {
        return $this->has($user, 'assessments.publish');
    }

    /** Report-card rollups are a dedicated read — see config/assessments.php. */
    public function viewReports(User $user): bool
    {
        return $this->has($user, 'assessments.reports.read');
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $this->has($user, 'assessments.exams.write')
            && $exam->status === ExamStatus::Draft
            && $exam->results()->count() === 0;
    }
}
