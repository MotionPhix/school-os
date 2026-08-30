<?php

declare(strict_types=1);

use App\Enums\AttendanceSessionStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\Invoice;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
});

it('soft-deletes a student via the API and hides it from queries', function (): void {
    $user = User::factory()->create();
    makeMember($user, $this->tenant, ['people.students.write', 'people.students.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->deleteJson("/api/v1/people/students/{$this->student->id}")
        ->assertStatus(204);

    $this->assertNotNull(DB::table('students')->where('id', $this->student->id)->value('deleted_at'));

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson("/api/v1/people/students/{$this->student->id}")
        ->assertStatus(404);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/people/students')
        ->assertJsonMissing(['id' => $this->student->id]);

    // Archive can be undone.
    Student::withTrashed()->findOrFail($this->student->id)->restore();

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson("/api/v1/people/students/{$this->student->id}")
        ->assertStatus(200);
});

it('allows reusing a campus code after the previous campus is archived', function (): void {
    $user = User::factory()->create();
    makeMember($user, $this->tenant, ['institution.campuses.write', 'institution.campuses.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->deleteJson("/api/v1/institution/campuses/{$this->campus->id}")
        ->assertStatus(204);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/institution/campuses', [
            'name' => 'Main Campus II',
            'code' => 'MAIN',
            'status' => 'operational',
            'address_line' => '2 Test Road',
            'city' => 'Lilongwe',
            'region' => 'Central',
            'timezone' => 'Africa/Blantyre',
        ])
        ->assertStatus(201);
});

it('keeps finance history intact when a student is archived', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-'.strtoupper(Str::random(8)),
        'student_id' => $this->student->id,
        'student_name' => $this->student->full_name,
        'student_initials' => 'AL',
        'grade_label' => 'Grade 5',
        'guardian_name' => 'Grace Hopper',
        'academic_year_label' => '2026',
        'term_label' => 'Term 1',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(20)->toDateString(),
        'currency' => 'MWK',
        'subtotal_minor' => 10000,
        'discount_minor' => 0,
        'total_minor' => 10000,
        'paid_minor' => 0,
        'balance_minor' => 10000,
        'status' => 'draft',
    ]);

    $this->student->delete();

    $this->assertDatabaseHas('finance_invoices', [
        'id' => $invoice->id,
        'student_id' => $this->student->id,
    ]);
});

it('excludes archived students from the attendance summary', function (): void {
    $section = CourseSection::create([
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

    enrollDirectly($section, $this->student, $this->tenant);

    $session = AttendanceSession::create([
        'tenant_id' => $this->tenant->id,
        'course_section_id' => $section->id,
        'date' => '2026-09-10',
        'period' => 1,
        'status' => AttendanceSessionStatus::Submitted,
        'present_count' => 1,
        'absent_count' => 0,
        'late_count' => 0,
        'excused_count' => 0,
        'total_count' => 1,
    ]);

    AttendanceMark::create([
        'tenant_id' => $this->tenant->id,
        'session_id' => $session->id,
        'student_id' => $this->student->id,
        'status' => 'present',
    ]);

    $this->student->delete();

    $user = User::factory()->create();
    makeMember($user, $this->tenant, ['attendance.summary.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
