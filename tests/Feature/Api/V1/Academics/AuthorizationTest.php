<?php

declare(strict_types=1);

use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\Term;
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

    $this->subject = makeSubject($this->tenant);
    $this->term = Term::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'name' => 'Term 1',
        'sequence' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'status' => 'upcoming',
    ]);
});

function makeSubject(Tenant $tenant, array $overrides = []): Subject
{
    return Subject::create(array_merge([
        'tenant_id' => $tenant->id,
        'code' => 'MATH',
        'name' => 'Mathematics',
        'category' => 'core',
        'stages' => ['primary'],
        'is_core' => false,
        'credit_hours' => 4,
    ], $overrides));
}

function makeSection(
    Tenant $tenant,
    AcademicYear $year,
    Campus $campus,
    Subject $subject,
    StaffMember $staff,
    array $overrides = [],
): CourseSection {
    return CourseSection::create(array_merge([
        'tenant_id' => $tenant->id,
        'academic_year_id' => $year->id,
        'campus_id' => $campus->id,
        'subject_id' => $subject->id,
        'grade_label' => 'Grade 5',
        'section_label' => '5A',
        'teacher_id' => $staff->id,
        'capacity' => 32,
        'status' => 'draft',
    ], $overrides));
}

function makeAcademicStudent(Tenant $tenant, Campus $campus, array $overrides = []): Student
{
    return Student::create(array_merge([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
        'admission_number' => 'ADM-'.Str::uuid()->toString(),
        'full_name' => 'Ada Lovelace',
        'avatar_initials' => 'AL',
        'date_of_birth' => '2012-04-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
    ], $overrides));
}

describe('subject authorization', function (): void {
    it('rejects creating a subject without academics.subjects.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/subjects', [
                'code' => 'PHYS',
                'name' => 'Physics',
                'category' => 'science',
                'stages' => ['primary'],
            ])
            ->assertStatus(403);
    });

    it('allows creating a subject with academics.subjects.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.subjects.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/subjects', [
                'code' => 'PHYS',
                'name' => 'Physics',
                'category' => 'science',
                'stages' => ['primary'],
            ])
            ->assertStatus(201);
    });

    it('rejects a duplicate subject code with a friendly 422', function (): void {
        // $this->subject (code MATH) already exists from beforeEach.
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.subjects.write']);
        Sanctum::actingAs($user);

        // Lowercase input must still collide with the existing uppercase code.
        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/subjects', [
                'code' => 'math',
                'name' => 'Mathematics Again',
                'category' => 'core',
                'stages' => ['primary'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });
});

describe('course section authorization', function (): void {
    it('rejects creating a course section without academics.courses.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/courses', [
                'academic_year_id' => $this->year->id,
                'campus_id' => $this->campus->id,
                'subject_id' => $this->subject->id,
                'grade_label' => 'Grade 5',
                'section_label' => '5A',
                'teacher_id' => $this->staff->id,
                'capacity' => 32,
            ])
            ->assertStatus(403);
    });

    it('allows creating a course section with academics.courses.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.courses.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/courses', [
                'academic_year_id' => $this->year->id,
                'campus_id' => $this->campus->id,
                'subject_id' => $this->subject->id,
                'grade_label' => 'Grade 5',
                'section_label' => '5A',
                'teacher_id' => $this->staff->id,
                'capacity' => 32,
            ])
            ->assertStatus(201);
    });

    it('rejects a duplicate section label with a friendly 422', function (): void {
        makeSection($this->tenant, $this->year, $this->campus, $this->subject, $this->staff, ['section_label' => '5A']);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.courses.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/courses', [
                'academic_year_id' => $this->year->id,
                'campus_id' => $this->campus->id,
                'subject_id' => $this->subject->id,
                'grade_label' => 'Grade 5',
                'section_label' => '5A',
                'teacher_id' => $this->staff->id,
                'capacity' => 32,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('section_label');
    });
});

describe('enrollment invariants', function (): void {
    it('rejects enrolling without academics.courses.write', function (): void {
        $section = makeSection($this->tenant, $this->year, $this->campus, $this->subject, $this->staff);
        $student = makeAcademicStudent($this->tenant, $this->campus);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.courses.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/academics/courses/{$section->id}/enrollments", ['student_id' => $student->id])
            ->assertStatus(403);
    });

    it('rejects a duplicate enrollment instead of silently no-opping', function (): void {
        $section = makeSection($this->tenant, $this->year, $this->campus, $this->subject, $this->staff);
        $student = makeAcademicStudent($this->tenant, $this->campus);
        enrollDirectly($section, $student, $this->tenant);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.courses.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/academics/courses/{$section->id}/enrollments", ['student_id' => $student->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_id');
    });

    it('rejects grading a student who is not on the section roster', function (): void {
        $section = makeSection($this->tenant, $this->year, $this->campus, $this->subject, $this->staff);
        $student = makeAcademicStudent($this->tenant, $this->campus); // not enrolled

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.gradebook.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/gradebook', [
                'course_section_id' => $section->id,
                'term_id' => $this->term->id,
                'student_id' => $student->id,
                'continuous_assessment' => 20,
                'exam_score' => 30,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_id');
    });

    it('allows grading a student who is on the section roster', function (): void {
        $section = makeSection($this->tenant, $this->year, $this->campus, $this->subject, $this->staff);
        $student = makeAcademicStudent($this->tenant, $this->campus);
        enrollDirectly($section, $student, $this->tenant);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.gradebook.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/academics/gradebook', [
                'course_section_id' => $section->id,
                'term_id' => $this->term->id,
                'student_id' => $student->id,
                'continuous_assessment' => 20,
                'exam_score' => 30,
            ])
            ->assertStatus(201);
    });
});

describe('tenant isolation', function (): void {
    it('returns 404 for a course section of another tenant', function (): void {
        $otherTenant = makeTenant();
        $campusB = Campus::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Campus',
            'code' => 'OTHER',
            'status' => 'operational',
            'address_line' => '3 Test Road',
            'city' => 'Blantyre',
            'region' => 'Southern',
            'timezone' => 'Africa/Blantyre',
        ]);
        $yearB = AcademicYear::create([
            'tenant_id' => $otherTenant->id,
            'label' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-08-31',
            'status' => 'planning',
            'is_current' => false,
        ]);
        $staffB = StaffMember::create([
            'tenant_id' => $otherTenant->id,
            'campus_id' => $campusB->id,
            'staff_number' => 'STF-'.Str::uuid()->toString(),
            'full_name' => 'Grace Hopper',
            'avatar_initials' => 'GH',
            'title' => 'Teacher',
            'department' => 'Maths',
            'subjects_taught' => ['Mathematics'],
            'hired_on' => '2023-02-01',
        ]);
        $subjectB = makeSubject($otherTenant, ['code' => 'ENG', 'name' => 'English']);
        $sectionB = makeSection($otherTenant, $yearB, $campusB, $subjectB, $staffB);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['academics.courses.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/academics/courses/{$sectionB->id}")
            ->assertStatus(404);
    });
});
