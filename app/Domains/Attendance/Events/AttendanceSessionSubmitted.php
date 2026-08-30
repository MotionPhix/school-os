<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Events;

use App\Models\AttendanceSession;
use App\Support\Events\BusinessEvent;

final class AttendanceSessionSubmitted extends BusinessEvent
{
    public function __construct(public readonly AttendanceSession $session)
    {
        parent::__construct($session->tenant_id);
    }

    public function name(): string
    {
        return 'attendance.session.submitted';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->session->id,
            'course_section_id' => $this->session->course_section_id,
            'date' => $this->session->date?->toDateString(),
            'period' => $this->session->period,
            'present_count' => $this->session->present_count,
            'absent_count' => $this->session->absent_count,
            'late_count' => $this->session->late_count,
            'excused_count' => $this->session->excused_count,
            'total_count' => $this->session->total_count,
            'taken_at' => $this->session->taken_at?->toIso8601String(),
        ];
    }
}
