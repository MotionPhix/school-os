<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Events\AttendanceSessionOpened;
use App\Domains\Attendance\Support\SessionCounts;
use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Open (or return) a draft register for a course section on a given
 * date + period. Snapshots the current course roster into
 * AttendanceMark rows with default status = Present so teachers only
 * flip the exceptions.
 */
final class OpenAttendanceSession
{
    /**
     * @param  array{date:string,period:int}  $data
     */
    public function handle(CourseSection $section, array $data, ?User $actor = null): AttendanceSession
    {
        return DB::transaction(function () use ($section, $data, $actor): AttendanceSession {
            /** @var AttendanceSession|null $existing */
            $existing = AttendanceSession::query()
                ->where('course_section_id', $section->id)
                ->whereDate('date', $data['date'])
                ->where('period', (int) $data['period'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $roster = $section->students()->get();
            if ($roster->isEmpty()) {
                throw ValidationException::withMessages([
                    'course_section_id' => 'Course section has no enrolled students.',
                ]);
            }

            $session = new AttendanceSession;
            $session->fill([
                'tenant_id' => $section->tenant_id,
                'course_section_id' => $section->id,
                'date' => $data['date'],
                'period' => (int) $data['period'],
                'status' => AttendanceSessionStatus::Draft->value,
                'opened_by' => $actor?->id,
            ]);
            $session->save();

            $marks = [];
            foreach ($roster as $student) {
                $mark = new AttendanceMark;
                $mark->fill([
                    'tenant_id' => $section->tenant_id,
                    'session_id' => $session->id,
                    'student_id' => $student->id,
                    'status' => AttendanceStatus::Present->value,
                    'marked_by' => $actor?->id,
                ]);
                $mark->save();
                $marks[] = $mark;
            }

            SessionCounts::apply($session, $marks);
            $session->save();

            AttendanceSessionOpened::dispatch($session);

            return $session->refresh();
        });
    }
}
