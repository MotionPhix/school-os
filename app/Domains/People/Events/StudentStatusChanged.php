<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Support\Events\BusinessEvent;

final class StudentStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly Student $student,
        public readonly StudentStatus $from,
        public readonly StudentStatus $to,
    ) {
        parent::__construct($student->tenant_id);
    }

    public function name(): string
    {
        return 'people.student.status_changed';
    }

    public function payload(): array
    {
        return [
            'student_id' => $this->student->id,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
