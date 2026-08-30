<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\Student;
use App\Support\Events\BusinessEvent;

final class StudentUpdated extends BusinessEvent
{
    public function __construct(public readonly Student $student)
    {
        parent::__construct($student->tenant_id);
    }

    public function name(): string
    {
        return 'people.student.updated';
    }

    public function payload(): array
    {
        return [
            'student_id' => $this->student->id,
            'grade_label' => $this->student->grade_label,
            'stage' => $this->student->stage->value,
        ];
    }
}
