<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Support\Events\BusinessEvent;

final class StudentGuardianUnlinked extends BusinessEvent
{
    public function __construct(
        string $tenantId,
        public readonly string $studentId,
        public readonly string $guardianId,
    ) {
        parent::__construct($tenantId);
    }

    public function name(): string
    {
        return 'people.student_guardian.unlinked';
    }

    public function payload(): array
    {
        return [
            'student_id' => $this->studentId,
            'guardian_id' => $this->guardianId,
        ];
    }
}
