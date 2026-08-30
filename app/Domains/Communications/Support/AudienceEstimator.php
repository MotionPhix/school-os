<?php

declare(strict_types=1);

namespace App\Domains\Communications\Support;

use App\Enums\CommunicationAudience;
use App\Models\Guardian;
use App\Models\StaffMember;
use App\Models\Student;

/**
 * Resolves an audience enum to an actual recipient count for the
 * active tenant. Falls back to the static estimates in
 * `config/communications.php::audience_estimates` when the concrete
 * count cannot be computed (e.g. `custom` list materialised elsewhere).
 */
final class AudienceEstimator
{
    public function count(CommunicationAudience $audience): int
    {
        return match ($audience) {
            CommunicationAudience::WholeSchool => $this->studentsCount() + $this->guardiansCount() + $this->staffCount(),
            CommunicationAudience::Staff => $this->staffCount(),
            CommunicationAudience::Teachers => $this->teachersCount(),
            CommunicationAudience::Students => $this->studentsCount(),
            CommunicationAudience::Guardians => $this->guardiansCount(),
            CommunicationAudience::Class_,
            CommunicationAudience::Custom => (int) (config('communications.audience_estimates.'.$audience->value) ?? 0),
        };
    }

    private function studentsCount(): int
    {
        return (int) Student::query()->count();
    }

    private function guardiansCount(): int
    {
        return (int) Guardian::query()->count();
    }

    private function staffCount(): int
    {
        return (int) StaffMember::query()->count();
    }

    private function teachersCount(): int
    {
        return (int) StaffMember::query()
            ->where('category', 'teacher')
            ->count();
    }
}
