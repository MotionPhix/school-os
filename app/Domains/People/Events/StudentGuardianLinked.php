<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\Guardian;
use App\Models\Student;
use App\Support\Events\BusinessEvent;

final class StudentGuardianLinked extends BusinessEvent
{
    public function __construct(
        public readonly Student $student,
        public readonly Guardian $guardian,
        public readonly string $relationship,
        public readonly bool $isPrimary,
    ) {
        parent::__construct($student->tenant_id);
    }

    public function name(): string
    {
        return 'people.student_guardian.linked';
    }

    public function payload(): array
    {
        return [
            'student_id' => $this->student->id,
            'guardian_id' => $this->guardian->id,
            'relationship' => $this->relationship,
            'is_primary' => $this->isPrimary,
        ];
    }
}
