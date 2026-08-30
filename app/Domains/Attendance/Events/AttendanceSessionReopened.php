<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Events;

use App\Models\AttendanceSession;
use App\Support\Events\BusinessEvent;

final class AttendanceSessionReopened extends BusinessEvent
{
    public function __construct(public readonly AttendanceSession $session)
    {
        parent::__construct($session->tenant_id);
    }

    public function name(): string
    {
        return 'attendance.session.reopened';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->session->id,
            'course_section_id' => $this->session->course_section_id,
            'date' => $this->session->date?->toDateString(),
            'period' => $this->session->period,
        ];
    }
}
