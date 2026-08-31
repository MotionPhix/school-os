<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domains\Attendance\Services\BulkAttendanceAction;
use App\Domains\Attendance\Services\OpenAttendanceSession;
use App\Domains\Attendance\Services\ReopenAttendanceSession;
use App\Domains\Attendance\Services\SetAttendanceMark;
use App\Domains\Attendance\Services\SubmitAttendanceSession;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Attendance\BulkAttendanceSessionsRequest;
use App\Http\Requests\Api\V1\Attendance\BulkSetAttendanceMarksRequest;
use App\Http\Requests\Api\V1\Attendance\OpenAttendanceSessionRequest;
use App\Http\Requests\Api\V1\Attendance\SetAttendanceMarkRequest;
use App\Http\Resources\Api\V1\Attendance\AttendanceSessionResource;
use App\Http\Resources\Api\V1\Attendance\StudentAttendanceSummaryResource;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\CourseSection;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AttendanceSessionController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $query = AttendanceSession::query()
            ->with(['courseSection.subject', 'courseSection.teacher']);

        if ($sectionId = $request->string('course_section_id')->toString()) {
            $query->where('course_section_id', $sectionId);
        }
        if ($date = $request->string('date')->toString()) {
            $query->whereDate('date', $date);
        }
        if ($from = $request->string('from')->toString()) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('date', '<=', $to);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->orderByDesc('date')
            ->orderBy('period')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            AttendanceSessionResource::collection($paginator),
            $paginator,
        );
    }

    public function show(AttendanceSession $attendanceSession): JsonResponse
    {
        $this->authorize('view', $attendanceSession);

        $attendanceSession->load([
            'courseSection.subject',
            'courseSection.teacher',
            'marks.student',
        ]);

        return $this->respond(new AttendanceSessionResource($attendanceSession));
    }

    public function open(OpenAttendanceSessionRequest $request, OpenAttendanceSession $service): JsonResponse
    {
        $data = $request->validated();
        $section = CourseSection::query()->findOrFail($data['course_section_id']);

        $session = $service->handle($section, [
            'date' => $data['date'],
            'period' => (int) $data['period'],
        ], $request->user());

        $session->load(['courseSection.subject', 'courseSection.teacher', 'marks.student']);

        return $this->respond(
            new AttendanceSessionResource($session),
            $session->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function mark(SetAttendanceMarkRequest $request, AttendanceSession $attendanceSession, SetAttendanceMark $service): JsonResponse
    {
        $this->authorize('update', $attendanceSession);

        $data = $request->validated();
        $student = Student::query()->findOrFail($data['student_id']);

        $service->handle($attendanceSession, $student, [
            'status' => $data['status'],
            'minutes_late' => $data['minutes_late'] ?? null,
            'note' => array_key_exists('note', $data) ? $data['note'] : null,
        ], $request->user());

        $attendanceSession->refresh()->load([
            'courseSection.subject',
            'courseSection.teacher',
            'marks.student',
        ]);

        return $this->respond(new AttendanceSessionResource($attendanceSession));
    }

    /**
     * Apply one status to many students inside a draft register
     * (all present / all absent / remaining absent).
     */
    public function markBulk(BulkSetAttendanceMarksRequest $request, AttendanceSession $attendanceSession, BulkAttendanceAction $service): JsonResponse
    {
        $this->authorize('update', $attendanceSession);

        $data = $request->validated();

        $result = $service->markStudents(
            $attendanceSession,
            $data['student_ids'],
            AttendanceStatus::from($data['status']),
            isset($data['minutes_late']) ? (int) $data['minutes_late'] : null,
            $request->user(),
        );

        $attendanceSession->refresh()->load([
            'courseSection.subject',
            'courseSection.teacher',
            'marks.student',
        ]);

        return response()->json([
            'data' => (new AttendanceSessionResource($attendanceSession))->resolve(),
            'affected' => $result['affected'],
            'skipped' => $result['skipped'],
        ]);
    }

    /** Lifecycle batch across registers: submit | reopen | delete. */
    public function bulk(BulkAttendanceSessionsRequest $request, BulkAttendanceAction $service): JsonResponse
    {
        $data = $request->validated();

        $result = $service->lifecycle($data['ids'], $data['action']);

        return response()->json([
            'affected' => $result['affected'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function submit(AttendanceSession $attendanceSession, SubmitAttendanceSession $service): JsonResponse
    {
        $this->authorize('submit', $attendanceSession);

        $session = $service->handle($attendanceSession);
        $session->load(['courseSection.subject', 'courseSection.teacher', 'marks.student']);

        return $this->respond(new AttendanceSessionResource($session));
    }

    /** Unlock a submitted register so a correction can be made. */
    public function reopen(AttendanceSession $attendanceSession, ReopenAttendanceSession $service): JsonResponse
    {
        $this->authorize('reopen', $attendanceSession);

        $session = $service->handle($attendanceSession);
        $session->load(['courseSection.subject', 'courseSection.teacher', 'marks.student']);

        return $this->respond(new AttendanceSessionResource($session));
    }

    public function destroy(AttendanceSession $attendanceSession): JsonResponse
    {
        $this->authorize('delete', $attendanceSession);
        $attendanceSession->delete();

        return $this->respondNoContent();
    }

    /**
     * Per-student rollup across submitted sessions.
     *
     * Filters: `course_section_id`, `campus_id`, `grade`, `from`, `to`,
     * plus a post-aggregate `risk` band (`at_risk` < 90%, `critical` < 80%,
     * `perfect` == 100%).
     * Only submitted sessions contribute so drafts don't pollute rates.
     */
    public function summary(Request $request): JsonResponse
    {
        if (! $request->user()?->can('viewSummary', AttendanceSession::class)) {
            abort(403);
        }

        $tenantId = app(TenantContext::class)->id();

        // The joined tables are not covered by the tenant global scope, so
        // filter them explicitly (defense in depth).
        $query = AttendanceMark::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_marks.session_id')
            ->join('students', 'students.id', '=', 'attendance_marks.student_id')
            ->where('attendance_sessions.tenant_id', $tenantId)
            ->where('students.tenant_id', $tenantId)
            ->whereNull('students.deleted_at')
            ->where('attendance_sessions.status', 'submitted');

        if ($sectionId = $request->string('course_section_id')->toString()) {
            $query->where('attendance_sessions.course_section_id', $sectionId);
        }
        if ($campusId = $request->string('campus_id')->toString()) {
            $query->where('students.campus_id', $campusId);
        }
        if (($grade = $request->string('grade')->toString()) && $grade !== 'all') {
            $query->where('students.grade_label', $grade);
        }
        if ($from = $request->string('from')->toString()) {
            $query->whereDate('attendance_sessions.date', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('attendance_sessions.date', '<=', $to);
        }

        $rows = $query
            ->groupBy(
                'students.id',
                'students.full_name',
                'students.avatar_initials',
                'students.grade_label',
            )
            ->orderBy('students.full_name')
            ->get([
                'students.id as student_id',
                'students.full_name as student_name',
                'students.avatar_initials as student_initials',
                'students.grade_label as grade_label',
                DB::raw('COUNT(*) as sessions'),
                DB::raw("SUM(CASE WHEN attendance_marks.status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendance_marks.status = 'absent'  THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN attendance_marks.status = 'late'    THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN attendance_marks.status = 'excused' THEN 1 ELSE 0 END) as excused"),
            ]);

        $risk = $request->string('risk')->toString();
        if ($risk !== '' && $risk !== 'all') {
            $rows = $rows->filter(function ($row) use ($risk): bool {
                $sessions = max(1, (int) $row->sessions);
                $presentLike = (int) $row->present + (int) $row->late;

                // Exact integer comparison — float division can drift at the
                // boundaries (e.g. 9/10 → 90.0) and mis-bucket a student.
                // at_risk  < 90% ⇔ (p+l)·10 < 9·sessions
                // critical < 80% ⇔ (p+l)·10 < 8·sessions
                // perfect  = 100% ⇔ (p+l)·10 ≥ 10·sessions
                return match ($risk) {
                    'at_risk' => $presentLike * 10 < 9 * $sessions,
                    'critical' => $presentLike * 10 < 8 * $sessions,
                    'perfect' => $presentLike * 10 >= 10 * $sessions,
                    default => true,
                };
            })->values();
        }

        return response()->json([
            'data' => StudentAttendanceSummaryResource::collection($rows)->resolve(),
        ]);
    }
}
