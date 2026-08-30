<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Events;

use App\Models\AttendanceMark;
use App\Support\Events\BusinessEvent;

final class AttendanceMarkChanged extends BusinessEvent
{
    public function __construct(public readonly AttendanceMark $mark)
    {
        parent::__construct($mark->tenant_id);
    }

    public function name(): string
    {
        return 'attendance.mark.changed';
    }

    public function payload(): array
    {
        return [
            'mark_id' => $this->mark->id,
            'session_id' => $this->mark->session_id,
            'student_id' => $this->mark->student_id,
            'status' => $this->mark->status->value,
            'minutes_late' => $this->mark->minutes_late,
        ];
    }
}
