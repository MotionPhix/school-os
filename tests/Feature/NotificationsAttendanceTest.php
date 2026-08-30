<?php

declare(strict_types=1);

use App\Domains\Attendance\Events\AttendanceSessionSubmitted;
use App\Enums\AttendanceSessionStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->campus = Campus::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
        'code' => 'MAIN',
        'status' => 'operational',
        'address_line' => '1 Test Road',
        'city' => 'Lilongwe',
        'region' => 'Central',
        'timezone' => 'Africa/Blantyre',
    ]);

    $this->year = AcademicYear::create([
        'tenant_id' => $this->tenant->id,
        'label' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
        'status' => 'planning',
        'is_current' => false,
    ]);

    $this->subject = Subject::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MATH',
        'name' => 'Mathematics',
        'category' => 'core',
        'stages' => ['primary'],
        'is_core' => false,
        'credit_hours' => 4,
    ]);

    $this->staff = StaffMember::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'staff_number' => 'STF-'.Str::uuid()->toString(),
        'full_name' => 'Alan Turing',
        'avatar_initials' => 'AT',
        'title' => 'Teacher',
        'department' => 'Science',
        'subjects_taught' => ['Mathematics'],
        'hired_on' => '2024-01-15',
    ]);

    $this->section = CourseSection::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'campus_id' => $this->campus->id,
        'subject_id' => $this->subject->id,
        'grade_label' => 'Grade 5',
        'section_label' => '5A',
        'teacher_id' => $this->staff->id,
        'capacity' => 32,
        'status' => 'draft',
    ]);

    $this->guardianUser = User::factory()->create(['name' => 'Guardian G']);
    makeMember($this->guardianUser, $this->tenant, []);
    $this->otherGuardianUser = User::factory()->create(['name' => 'Guardian H']);
    makeMember($this->otherGuardianUser, $this->tenant, []);

    $this->makeStudent = function (string $name): Student {
        return Student::create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'admission_number' => 'ADM-'.Str::uuid()->toString(),
            'full_name' => $name,
            'avatar_initials' => 'XX',
            'date_of_birth' => '2012-04-01',
            'stage' => 'primary',
            'grade_label' => 'Grade 5',
            'status' => 'enrolled',
        ]);
    };

    $this->linkGuardian = function (Student $student, string $guardianName, ?string $userId): void {
        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'full_name' => $guardianName,
            'avatar_initials' => 'XX',
            'user_id' => $userId,
        ]);

        DB::table('student_guardians')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'Parent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    $this->makeSession = function (array $marks): AttendanceSession {
        $session = AttendanceSession::create([
            'tenant_id' => $this->tenant->id,
            'course_section_id' => $this->section->id,
            'date' => '2026-09-10',
            'period' => 1,
            'status' => AttendanceSessionStatus::Submitted,
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'excused_count' => 0,
            'total_count' => count($marks),
        ]);

        foreach ($marks as $studentId => $status) {
            AttendanceMark::create([
                'tenant_id' => $this->tenant->id,
                'session_id' => $session->id,
                'student_id' => $studentId,
                'status' => $status,
            ]);
        }

        return $session;
    };

    $this->studentA = ($this->makeStudent)('Ada Lovelace');
    $this->studentB = ($this->makeStudent)('Grace Hopper');

    enrollDirectly($this->section, $this->studentA, $this->tenant);
    enrollDirectly($this->section, $this->studentB, $this->tenant);

    ($this->linkGuardian)($this->studentA, 'Grace Parent', $this->guardianUser->id);
    ($this->linkGuardian)($this->studentB, 'Alan Parent', $this->otherGuardianUser->id);
});

it('alerts the guardian of an absent student, not of a present one', function (): void {
    $session = ($this->makeSession)([$this->studentA->id => 'absent', $this->studentB->id => 'present']);

    event(new AttendanceSessionSubmitted($session));

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->guardianUser->id]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->otherGuardianUser->id]);

    $notification = Notification::query()
        ->where('notifiable_id', $this->guardianUser->id)
        ->first();

    expect($notification->data['title'])->toBe('Absence alert');
    expect($notification->data['body'])->toContain('Ada Lovelace');
    expect($notification->data['body'])->not->toContain('Grace Hopper');
});

it('does not alert for late or excused marks', function (): void {
    $session = ($this->makeSession)([$this->studentA->id => 'late', $this->studentB->id => 'excused']);

    event(new AttendanceSessionSubmitted($session));

    $this->assertDatabaseCount('notifications', 0);
});

it('skips guardians without a portal account', function (): void {
    $noPortalGuardian = Guardian::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'No Portal',
        'avatar_initials' => 'NP',
        'user_id' => null,
    ]);

    DB::table('student_guardians')->where('student_id', $this->studentA->id)->update(['guardian_id' => $noPortalGuardian->id]);

    $session = ($this->makeSession)([$this->studentA->id => 'absent', $this->studentB->id => 'present']);

    event(new AttendanceSessionSubmitted($session));

    $this->assertDatabaseCount('notifications', 0);
});
