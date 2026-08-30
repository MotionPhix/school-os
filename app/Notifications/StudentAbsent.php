<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Attendance\Events\AttendanceSessionSubmitted;
use App\Enums\AttendanceStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * In-app absence alert to a guardian whose child was marked absent in a
 * submitted register. The payload lists the recipient's own children who
 * were absent that session.
 */
final class StudentAbsent extends SchoolNotification
{
    public function __construct(private readonly AttendanceSessionSubmitted $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $session = $this->event->session;

        $absentStudentIds = DB::table('attendance_marks')
            ->where('session_id', $session->id)
            ->where('status', AttendanceStatus::Absent->value)
            ->pluck('student_id');

        $names = collect();
        if ($notifiable instanceof User) {
            $names = DB::table('student_guardians')
                ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
                ->join('students', 'students.id', '=', 'student_guardians.student_id')
                ->where('guardians.user_id', $notifiable->id)
                ->whereIn('student_guardians.student_id', $absentStudentIds)
                ->pluck('students.full_name');
        }

        return [
            'kind' => 'attendance',
            'title' => 'Absence alert',
            'body' => $names->isEmpty()
                ? "A student was marked absent on {$session->date}."
                : $names->implode(', ')." marked absent on {$session->date}.",
            'href' => "/attendance/sessions/{$session->id}",
        ];
    }
}
