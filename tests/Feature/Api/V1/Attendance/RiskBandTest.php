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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Track 4 — attendance risk-band boundaries. The summary filter uses exact
 * integer math, so a student at exactly 90% / 80% / 100% lands in the
 * correct bucket and late marks count as present-like.
 */
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

    $this->makeStudent = function (string $name): Student {
        return Student::create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'admission_number' => 'ADM-'.Str::uuid()->toString(),
            'full_name' => $name,
            'avatar_initials' => strtoupper(substr($name, 0, 2)),
            'date_of_birth' => '2012-04-01',
            'stage' => 'primary',
            'grade_label' => 'Grade 5',
            'status' => 'enrolled',
        ]);
    };

    $this->students = [
        'ada' => ($this->makeStudent)('Ada Lovelace'),
        'grace' => ($this->makeStudent)('Grace Hopper'),
        'alan' => ($this->makeStudent)('Alan Kay'),
        'katherine' => ($this->makeStudent)('Katherine Johnson'),
        'margaret' => ($this->makeStudent)('Margaret Hamilton'),
    ];

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

    $this->user = User::factory()->create();
    makeMember($this->user, $this->tenant, ['attendance.summary.read']);
    Sanctum::actingAs($this->user);

    /**
     * Ten submitted sessions; each student gets one mark per session:
     *   ada       9 present + 1 absent            → 90% (not at_risk, not perfect)
     *   grace     8 present + 2 absent            → 80% (at_risk, not critical)
     *   alan      7 present + 3 absent            → 70% (critical)
     *   katherine 10 present                      → 100% (perfect)
     *   margaret  8 present + 1 late + 1 absent   → 90% (late counts as present-like)
     */
    for ($i = 0; $i < 10; $i++) {
        $session = AttendanceSession::create([
            'tenant_id' => $this->tenant->id,
            'course_section_id' => $this->section->id,
            'date' => '2026-09-'.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT),
            'period' => 1,
            'status' => AttendanceSessionStatus::Submitted,
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'excused_count' => 0,
            'total_count' => 0,
        ]);

        $marks = [
            'ada' => $i < 9 ? 'present' : 'absent',
            'grace' => $i < 8 ? 'present' : 'absent',
            'alan' => $i < 7 ? 'present' : 'absent',
            'katherine' => 'present',
            'margaret' => $i < 8 ? 'present' : ($i === 8 ? 'late' : 'absent'),
        ];

        foreach ($marks as $key => $status) {
            AttendanceMark::create([
                'tenant_id' => $this->tenant->id,
                'session_id' => $session->id,
                'student_id' => $this->students[$key]->id,
                'status' => $status,
            ]);
        }
    }
});

function riskNames(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('student_name')->sort()->values()->all();
}

it('reports every student without a risk filter', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    expect(riskNames($this->withHeader('X-Tenant-Id', $this->tenant->id)->getJson('/api/v1/attendance/summary')))
        ->toBe(['Ada Lovelace', 'Alan Kay', 'Grace Hopper', 'Katherine Johnson', 'Margaret Hamilton']);
});

it('treats exactly 90% as not at risk (and late counts as present-like)', function (): void {
    $names = riskNames($this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary?risk=at_risk'));

    expect($names)->toBe(['Alan Kay', 'Grace Hopper']);
});

it('treats exactly 80% as at risk but not critical', function (): void {
    $names = riskNames($this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary?risk=critical'));

    expect($names)->toBe(['Alan Kay']);
});

it('flags only 100% attendance as perfect', function (): void {
    $names = riskNames($this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary?risk=perfect'));

    expect($names)->toBe(['Katherine Johnson']);
});

it('ignores draft sessions in the summary rollup', function (): void {
    $orphan = ($this->makeStudent)('No One Listens');
    $draft = AttendanceSession::create([
        'tenant_id' => $this->tenant->id,
        'course_section_id' => $this->section->id,
        'date' => '2026-09-20',
        'period' => 2,
        'status' => AttendanceSessionStatus::Draft,
        'present_count' => 0,
        'absent_count' => 0,
        'late_count' => 0,
        'excused_count' => 0,
        'total_count' => 0,
    ]);
    AttendanceMark::create([
        'tenant_id' => $this->tenant->id,
        'session_id' => $draft->id,
        'student_id' => $orphan->id,
        'status' => 'absent',
    ]);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonMissing(['student_name' => 'No One Listens']);
});

it('isolates the summary per tenant', function (): void {
    $otherTenant = makeTenant();
    $otherCampus = Campus::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Campus',
        'code' => 'OTH',
        'status' => 'operational',
        'address_line' => '2 Other Road',
        'city' => 'Blantyre',
        'region' => 'Southern',
        'timezone' => 'Africa/Blantyre',
    ]);
    $otherStudent = Student::create([
        'tenant_id' => $otherTenant->id,
        'campus_id' => $otherCampus->id,
        'admission_number' => 'ADM-'.Str::uuid()->toString(),
        'full_name' => 'Foreign Student',
        'avatar_initials' => 'FS',
        'date_of_birth' => '2011-01-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
    ]);
    $otherSession = AttendanceSession::create([
        'tenant_id' => $otherTenant->id,
        'course_section_id' => $this->section->id,
        'date' => '2026-09-21',
        'period' => 1,
        'status' => AttendanceSessionStatus::Submitted,
        'present_count' => 0,
        'absent_count' => 0,
        'late_count' => 0,
        'excused_count' => 0,
        'total_count' => 0,
    ]);
    AttendanceMark::create([
        'tenant_id' => $otherTenant->id,
        'session_id' => $otherSession->id,
        'student_id' => $otherStudent->id,
        'status' => 'present',
    ]);

    $names = riskNames($this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/attendance/summary'));

    expect($names)->not->toContain('Foreign Student')
        ->and(count($names))->toBe(5);
});
