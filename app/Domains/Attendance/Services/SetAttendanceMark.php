<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Events\AttendanceMarkChanged;
use App\Domains\Attendance\Support\SessionCounts;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Set (or update) the attendance status for one student inside a draft
 * session. Submitted sessions are locked and reject writes.
 */
final class SetAttendanceMark
{
    /**
     * @param  array{status:string,minutes_late?:int|null,note?:?string}  $data
     */
    public function handle(AttendanceSession $session, Student $student, array $data, ?User $actor = null): AttendanceMark
    {
        return DB::transaction(function () use ($session, $student, $data, $actor): AttendanceMark {
            if ($session->status->isLocked()) {
                throw ValidationException::withMessages([
                    'session_id' => 'Session is submitted and cannot be edited.',
                ]);
            }

            $status = AttendanceStatus::from((string) $data['status']);

            /** @var AttendanceMark|null $mark */
            $mark = $session->marks()->where('student_id', $student->id)->first();
            if ($mark === null) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student is not on this session roster.',
                ]);
            }

            $mark->fill([
                'status' => $status->value,
                'minutes_late' => $status === AttendanceStatus::Late
                    ? (int) ($data['minutes_late'] ?? $mark->minutes_late ?? config('attendance.defaults.minutes_late', 5))
                    : null,
                'note' => array_key_exists('note', $data) ? $data['note'] : $mark->note,
                'marked_by' => $actor?->id ?? $mark->marked_by,
            ]);
            $mark->save();

            $marks = $session->marks()->get();
            SessionCounts::apply($session, $marks);
            $session->save();

            AttendanceMarkChanged::dispatch($mark);

            return $mark->refresh();
        });
    }
}
