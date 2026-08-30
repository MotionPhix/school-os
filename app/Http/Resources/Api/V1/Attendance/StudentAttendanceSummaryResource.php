<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Attendance;

use App\Http\Resources\Api\V1\CapabilityResource;
use Illuminate\Http\Request;

/**
 * Per-student rollup — mirrors src/contracts/attendance.ts::StudentAttendanceSummary.
 *
 * Fed by AttendanceController@summary from an aggregated query, not a
 * database model, so this resource wraps an array/stdClass shape.
 */
final class StudentAttendanceSummaryResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        $sessions = (int) ($this['sessions'] ?? 0);
        $present = (int) ($this['present'] ?? 0);
        $late = (int) ($this['late'] ?? 0);

        return [
            'student_id' => (string) ($this['student_id'] ?? ''),
            'student_name' => (string) ($this['student_name'] ?? ''),
            'student_initials' => (string) ($this['student_initials'] ?? ''),
            'grade_label' => (string) ($this['grade_label'] ?? ''),
            'sessions' => $sessions,
            'present' => $present,
            'absent' => (int) ($this['absent'] ?? 0),
            'late' => $late,
            'excused' => (int) ($this['excused'] ?? 0),
            'rate' => $sessions > 0 ? round((($present + $late) / $sessions) * 100, 2) : 0.0,
        ];
    }
}
