<?php

declare(strict_types=1);

use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
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

    $this->term = Term::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'name' => 'Term 1',
        'sequence' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'status' => 'upcoming',
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

    $this->period = ExamPeriod::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'term_id' => $this->term->id,
        'name' => 'Term 1 Mid-Term',
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-10',
        'status' => 'draft',
    ]);

    $this->makeExam = function (array $overrides = []): Exam {
        return Exam::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'period_id' => $this->period->id,
            'course_section_id' => $this->section->id,
            'paper_title' => 'Paper 1 — Algebra',
            'scheduled_on' => '2026-10-05',
            'starts_at' => '09:00',
            'duration_minutes' => 90,
            'max_score' => 100,
            'pass_mark' => 40,
            'status' => 'draft',
        ], $overrides));
    };
});

describe('exam authorization', function (): void {
    it('rejects creating an exam without assessments.exams.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/assessments/exams', [
                'period_id' => $this->period->id,
                'course_section_id' => $this->section->id,
                'paper_title' => 'Paper 1 — Algebra',
                'scheduled_on' => '2026-10-05',
                'starts_at' => '09:00',
                'duration_minutes' => 90,
                'max_score' => 100,
                'pass_mark' => 40,
            ])
            ->assertStatus(403);
    });

    it('allows creating an exam with assessments.exams.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['assessments.exams.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/assessments/exams', [
                'period_id' => $this->period->id,
                'course_section_id' => $this->section->id,
                'paper_title' => 'Paper 1 — Algebra',
                'scheduled_on' => '2026-10-05',
                'starts_at' => '09:00',
                'duration_minutes' => 90,
                'max_score' => 100,
                'pass_mark' => 40,
            ])
            ->assertStatus(201);
    });

    it('rejects recording a result without assessments.results.write', function (): void {
        $exam = ($this->makeExam)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['assessments.exams.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/assessments/exams/{$exam->id}/results", [
                'student_id' => $this->student->id,
                'score' => 45,
            ])
            ->assertStatus(403);
    });

    it('rejects publishing without the dedicated assessments.publish key', function (): void {
        $exam = ($this->makeExam)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['assessments.exams.write']); // write, but NOT publish
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/assessments/exams/{$exam->id}/status", ['status' => 'published'])
            ->assertStatus(403);
    });

    it('returns 404 for an exam of another tenant', function (): void {
        $otherTenant = makeTenant();
        $examB = Exam::create([
            'tenant_id' => $otherTenant->id,
            'period_id' => $this->period->id, // FK row never resolves — scope blocks first
            'course_section_id' => $this->section->id,
            'paper_title' => 'Paper 1 — Algebra',
            'scheduled_on' => '2026-10-05',
            'starts_at' => '09:00',
            'status' => 'draft',
        ]);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['assessments.exams.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/assessments/exams/{$examB->id}")
            ->assertStatus(404);
    });
});

describe('report card authorization', function (): void {
    it('rejects report cards without the dedicated assessments.reports.read key', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['assessments.exams.read']); // exams.read, but NOT reports.read
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/v1/assessments/reports/term')
            ->assertStatus(403);
    });

    it('allows report cards with assessments.reports.read', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['assessments.exams.read', 'assessments.reports.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/v1/assessments/reports/term')
            ->assertStatus(200);
    });
});
