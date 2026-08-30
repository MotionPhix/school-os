<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Domains\Assessments\Events\ExamPublished;
use App\Models\CourseSection;
use App\Models\User;
use App\Support\Events\BusinessEvent;
use Illuminate\Support\Facades\DB;

/**
 * The portal user accounts of guardians linked to students enrolled in
 * the exam's section (guardians without a portal account are skipped).
 */
final class ExamGuardianRecipients implements ResolvesNotificationRecipients
{
    public function resolve(BusinessEvent $event): iterable
    {
        if (! $event instanceof ExamPublished) {
            return [];
        }

        $section = CourseSection::query()->find($event->exam->course_section_id);
        if ($section === null) {
            return [];
        }

        $userIds = DB::table('course_enrollments')
            ->join('student_guardians', 'student_guardians.student_id', '=', 'course_enrollments.student_id')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->where('course_enrollments.course_section_id', $section->id)
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
