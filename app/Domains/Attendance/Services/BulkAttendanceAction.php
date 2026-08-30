<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Batch attendance operations.
 *
 * Mirrors src/lib/verbs/attendance.ts. Every row is applied through the
 * ordinary single-record services so business events keep firing; rows
 * that violate a guard are skipped with a reason instead of failing the
 * whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkAttendanceAction
{
    public function __construct(
        private readonly SetAttendanceMark $setMark,
        private readonly SubmitAttendanceSession $submit,
        private readonly ReopenAttendanceSession $reopen,
    ) {}

    /**
     * Apply one status to many students inside a single draft register
     * (mark-all / mark-remaining flows).
     *
     * @param  array<int,string>  $studentIds
     * @return BulkResult
     */
    public function markStudents(
        AttendanceSession $session,
        array $studentIds,
        AttendanceStatus $status,
        ?int $minutesLate = null,
        ?User $actor = null,
    ): array {
        if ($session->status->isLocked()) {
            throw ValidationException::withMessages([
                'session_id' => 'Session is submitted and cannot be edited. Reopen it first.',
            ]);
        }

        $students = Student::query()->whereIn('id', $studentIds)->get();
        $skipped = [];
        $affected = 0;

        foreach ($students as $student) {
            try {
                $this->setMark->handle($session, $student, [
                    'status' => $status->value,
                    'minutes_late' => $minutesLate,
                ], $actor);
                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$student->full_name}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * Lifecycle batch across registers: submit | reopen | delete.
     *
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function lifecycle(array $ids, string $action): array
    {
        $sessions = AttendanceSession::query()
            ->with('courseSection.subject')
            ->whereIn('id', $ids)
            ->withCount('marks')
            ->get();

        $skipped = [];
        $affected = 0;

        foreach ($sessions as $session) {
            $label = $this->label($session);

            if ($action === 'submit') {
                if ($session->status === AttendanceSessionStatus::Submitted) {
                    $skipped[] = "{$label}: already submitted.";

                    continue;
                }
                if ((int) $session->marks_count === 0) {
                    $skipped[] = "{$label}: roster is empty.";

                    continue;
                }
                $this->submit->handle($session);
                $affected++;

                continue;
            }

            if ($action === 'reopen') {
                try {
                    $this->reopen->handle($session);
                    $affected++;
                } catch (ValidationException $e) {
                    $skipped[] = "{$label}: ".$e->getMessage();
                }

                continue;
            }

            if ($session->status->isLocked()) {
                $skipped[] = "{$label}: submitted registers cannot be deleted.";

                continue;
            }

            $session->delete();
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    private function label(AttendanceSession $session): string
    {
        return mb_trim(sprintf(
            '%s P%d %s',
            $session->courseSection?->subject?->code ?? 'Register',
            $session->period,
            $session->date?->toDateString() ?? '',
        ));
    }
}
