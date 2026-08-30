<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Domains\Attendance\Events\AttendanceSessionSubmitted;
use App\Enums\AttendanceStatus;
use App\Models\User;
use App\Support\Events\BusinessEvent;
use Illuminate\Support\Facades\DB;

/**
 * Portal user accounts of guardians linked to students marked `absent`
 * in the submitted attendance session. Late/excused marks do not
 * trigger an alert.
 */
final class AttendanceAbsentGuardianRecipients implements ResolvesNotificationRecipients
{
    public function resolve(BusinessEvent $event): iterable
    {
        if (! $event instanceof AttendanceSessionSubmitted) {
            return [];
        }

        $studentIds = DB::table('attendance_marks')
            ->where('session_id', $event->session->id)
            ->where('status', AttendanceStatus::Absent->value)
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return [];
        }

        $userIds = DB::table('student_guardians')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->whereIn('student_guardians.student_id', $studentIds)
            ->whereNotNull('guardians.user_id')
            ->pluck('guardians.user_id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()->whereIn('id', $userIds)->get();
    }
}
