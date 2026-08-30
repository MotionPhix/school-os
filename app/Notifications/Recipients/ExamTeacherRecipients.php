<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Domains\Assessments\Events\ExamPublished;
use App\Models\CourseSection;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Events\BusinessEvent;

/** The user account of the staff member teaching the exam's section. */
final class ExamTeacherRecipients implements ResolvesNotificationRecipients
{
    public function resolve(BusinessEvent $event): iterable
    {
        if (! $event instanceof ExamPublished) {
            return [];
        }

        $section = CourseSection::query()->find($event->exam->course_section_id);
        $staff = $section === null ? null : StaffMember::query()->find($section->teacher_id);

        if ($staff?->user_id === null) {
            return [];
        }

        return User::query()->whereKey($staff->user_id)->get();
    }
}
