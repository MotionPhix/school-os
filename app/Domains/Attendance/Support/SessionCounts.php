<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Support;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;

/**
 * Pure helpers for attendance count recomputation. Mirrors
 * src/contracts/attendance.ts::recomputeCounts on the SPA.
 */
final class SessionCounts
{
    /**
     * @param  iterable<AttendanceMark>  $marks
     * @return array{present_count:int,absent_count:int,late_count:int,excused_count:int,total_count:int}
     */
    public static function fromMarks(iterable $marks): array
    {
        $c = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        $total = 0;
        foreach ($marks as $m) {
            $total++;
            $key = $m->status instanceof AttendanceStatus ? $m->status->value : (string) $m->status;
            if (isset($c[$key])) {
                $c[$key]++;
            }
        }

        return [
            'present_count' => $c['present'],
            'absent_count' => $c['absent'],
            'late_count' => $c['late'],
            'excused_count' => $c['excused'],
            'total_count' => $total,
        ];
    }

    public static function apply(AttendanceSession $session, iterable $marks): void
    {
        $counts = self::fromMarks($marks);
        $session->fill($counts);
    }
}
