<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\Student;
use App\Support\Events\BusinessEvent;

final class StudentCreated extends BusinessEvent
{
    public function __construct(public readonly Student $student)
    {
        parent::__construct($student->tenant_id);
    }

    public function name(): string
    {
        return 'people.student.created';
    }

    public function payload(): array
    {
        return [
            'student_id' => $this->student->id,
            'admission_number' => $this->student->admission_number,
            'campus_id' => $this->student->campus_id,
            'status' => $this->student->status->value,
        ];
    }
}
