<?php

declare(strict_types=1);

use App\Enums\AttendanceSessionStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

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

    $this->student = Student::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'admission_number' => 'ADM-'.Str::uuid()->toString(),
        'full_name' => 'Ada Lovelace',
        'avatar_initials' => 'AL',
        'date_of_birth' => '2012-04-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
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

    enrollDirectly($this->section, $this->student, $this->tenant);

    $this->makeSession = function (array $overrides = [], ?string $markStatus = null): AttendanceSession {
        $session = AttendanceSession::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'course_section_id' => $this->section->id,
            'date' => '2026-09-10',
            'period' => 1,
            'status' => AttendanceSessionStatus::Draft,
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'excused_count' => 0,
            'total_count' => 0,
        ], $overrides));

        if ($markStatus !== null) {
            AttendanceMark::create([
                'tenant_id' => $this->tenant->id,
                'session_id' => $session->id,
                'student_id' => $this->student->id,
                'status' => $markStatus,
            ]);
        }

        return $session;
    };
});

describe('attendance session authorization', function (): void {
    it('rejects opening a session without attendance.sessions.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/attendance/sessions/open', [
                'course_section_id' => $this->section->id,
                'date' => '2026-09-10',
                'period' => 1,
            ])
            ->assertStatus(403);
    });

    it('allows opening a session with attendance.sessions.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.sessions.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/attendance/sessions/open', [
                'course_section_id' => $this->section->id,
                'date' => '2026-09-10',
                'period' => 1,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.course_section_id', $this->section->id);
    });

    it('rejects marking without attendance.marks.write', function (): void {
        $session = ($this->makeSession)([], 'present');
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.sessions.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/attendance/sessions/{$session->id}/marks", [
                'student_id' => $this->student->id,
                'status' => 'absent',
            ])
            ->assertStatus(403);
    });

    it('allows marking with attendance.marks.write', function (): void {
        $session = ($this->makeSession)([], 'present');
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.marks.write', 'attendance.sessions.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/attendance/sessions/{$session->id}/marks", [
                'student_id' => $this->student->id,
                'status' => 'absent',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('attendance_marks', [
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => 'absent',
        ]);
    });

    it('rejects submitting without attendance.sessions.write', function (): void {
        $session = ($this->makeSession)([], 'present');
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.marks.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/attendance/sessions/{$session->id}/submit")
            ->assertStatus(403);
    });

    it('returns 404 for a session of another tenant', function (): void {
        $otherTenant = makeTenant();
        $sessionB = AttendanceSession::create([
            'tenant_id' => $otherTenant->id,
            'course_section_id' => $this->section->id, // row never resolves — scope blocks first
            'date' => '2026-09-10',
            'period' => 2,
            'status' => 'draft',
        ]);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.sessions.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/attendance/sessions/{$sessionB->id}")
            ->assertStatus(404);
    });
});

describe('attendance summary authorization', function (): void {
    it('rejects the summary without the dedicated attendance.summary.read key', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.sessions.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/v1/attendance/summary')
            ->assertStatus(403);
    });

    it('allows the summary with attendance.summary.read', function (): void {
        $session = ($this->makeSession)(['status' => AttendanceSessionStatus::Submitted], 'present');

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['attendance.summary.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/v1/attendance/summary')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});
