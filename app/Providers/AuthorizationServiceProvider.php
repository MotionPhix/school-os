<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\AuditEvent;
use App\Models\Broadcast;
use App\Models\CalendarEvent;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\ExamResult;
use App\Models\FeeStructure;
use App\Models\GradebookEntry;
use App\Models\Guardian;
use App\Models\InstitutionProfile;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\PersonDocument;
use App\Models\Role;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Policies\Academics\CourseSectionPolicy;
use App\Policies\Academics\GradebookEntryPolicy;
use App\Policies\Academics\SubjectPolicy;
use App\Policies\Academics\TimetableSlotPolicy;
use App\Policies\AcademicYearPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\Assessments\ExamPeriodPolicy;
use App\Policies\Assessments\ExamPolicy;
use App\Policies\Assessments\ExamResultPolicy;
use App\Policies\Attendance\AttendanceMarkPolicy;
use App\Policies\Attendance\AttendanceSessionPolicy;
use App\Policies\AuditEventPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\CampusPolicy;
use App\Policies\Communications\AnnouncementPolicy;
use App\Policies\Communications\BroadcastPolicy;
use App\Policies\Communications\MessageThreadPolicy;
use App\Policies\Finance\AccountPolicy;
use App\Policies\Finance\FeeStructurePolicy;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\PaymentPolicy;
use App\Policies\InstitutionProfilePolicy;
use App\Policies\InvitationPolicy;
use App\Policies\People\GuardianPolicy;
use App\Policies\People\PersonDocumentPolicy;
use App\Policies\People\StaffMemberPolicy;
use App\Policies\People\StudentPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\TermPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Explicit policy registry.
 *
 * Laravel's policy auto-discovery only looks for `App\Policies\{Model}Policy`.
 * SchoolOS groups policies by capability (`App\Policies\People\StudentPolicy`,
 * …), which discovery cannot find — the Gate then denies every ability and the
 * API answers 403 even for a principal. Every capability policy is therefore
 * mapped here by hand.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        // Identity & Access
        Tenant::class => TenantPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Invitation::class => InvitationPolicy::class,
        AuditEvent::class => AuditEventPolicy::class,

        // Institution
        InstitutionProfile::class => InstitutionProfilePolicy::class,
        Campus::class => CampusPolicy::class,
        AcademicYear::class => AcademicYearPolicy::class,
        Term::class => TermPolicy::class,
        CalendarEvent::class => CalendarEventPolicy::class,

        // People
        Student::class => StudentPolicy::class,
        Guardian::class => GuardianPolicy::class,
        StaffMember::class => StaffMemberPolicy::class,
        PersonDocument::class => PersonDocumentPolicy::class,

        // Admissions
        Application::class => ApplicationPolicy::class,

        // Academics
        Subject::class => SubjectPolicy::class,
        CourseSection::class => CourseSectionPolicy::class,
        TimetableSlot::class => TimetableSlotPolicy::class,
        GradebookEntry::class => GradebookEntryPolicy::class,

        // Attendance
        AttendanceSession::class => AttendanceSessionPolicy::class,
        AttendanceMark::class => AttendanceMarkPolicy::class,

        // Assessments & Exams
        ExamPeriod::class => ExamPeriodPolicy::class,
        Exam::class => ExamPolicy::class,
        ExamResult::class => ExamResultPolicy::class,

        // Finance & Billing
        Account::class => AccountPolicy::class,
        FeeStructure::class => FeeStructurePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,

        // Communications
        Announcement::class => AnnouncementPolicy::class,
        MessageThread::class => MessageThreadPolicy::class,
        Broadcast::class => BroadcastPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
