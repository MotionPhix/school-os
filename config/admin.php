<?php

declare(strict_types=1);
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\Broadcast;
use App\Models\CalendarEvent;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

/*
|--------------------------------------------------------------------------
| Admin & Trash
|--------------------------------------------------------------------------
|
| Resources that can be restored from the trash by operators holding
| `platform.trash.restore`. The slug is the URL segment used by
| POST /api/v1/admin/trash/{resource}/{id}/restore.
*/

return [
    'restoreable' => [
        'students' => Student::class,
        'guardians' => Guardian::class,
        'staff_members' => StaffMember::class,
        'campuses' => Campus::class,
        'academic_years' => AcademicYear::class,
        'terms' => Term::class,
        'subjects' => Subject::class,
        'course_sections' => CourseSection::class,
        'applications' => Application::class,
        'announcements' => Announcement::class,
        'broadcasts' => Broadcast::class,
        'calendar_events' => CalendarEvent::class,
        'fee_structures' => FeeStructure::class,
        'invoices' => Invoice::class,
        'message_threads' => MessageThread::class,
    ],
];
