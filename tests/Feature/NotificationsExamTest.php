<?php

declare(strict_types=1);

use App\Domains\Assessments\Events\ExamPublished;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
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

    $this->period = ExamPeriod::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'term_id' => $this->term->id,
        'name' => 'Mid-Term',
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-10',
        'status' => 'draft',
    ]);

    $this->teacherUser = User::factory()->create(['name' => 'Teacher T']);
    makeMember($this->teacherUser, $this->tenant, []);

    $this->guardianUser = User::factory()->create(['name' => 'Guardian G']);
    makeMember($this->guardianUser, $this->tenant, []);

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
        'user_id' => $this->teacherUser->id,
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

    $this->guardian = Guardian::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Grace Hopper',
        'avatar_initials' => 'GH',
        'user_id' => $this->guardianUser->id,
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
    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student->id,
        'guardian_id' => $this->guardian->id,
        'relationship' => 'Parent',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->exam = Exam::create([
        'tenant_id' => $this->tenant->id,
        'period_id' => $this->period->id,
        'course_section_id' => $this->section->id,
        'paper_title' => 'Paper 1 — Algebra',
        'scheduled_on' => '2026-10-05',
        'starts_at' => '09:00',
        'duration_minutes' => 90,
        'max_score' => 100,
        'pass_mark' => 40,
        'status' => 'published',
    ]);
});

it('notifies the section teacher and linked guardians when results are published', function (): void {
    event(new ExamPublished($this->exam));

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->teacherUser->id]);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->guardianUser->id]);

    $guardianNotification = Notification::query()
        ->where('notifiable_id', $this->guardianUser->id)
        ->first();

    expect($guardianNotification->data['title'])->toBe('Results published: Paper 1 — Algebra');
    expect($guardianNotification->data['kind'])->toBe('results');
});

it('skips staff and guardians without a portal user account', function (): void {
    $this->staff->update(['user_id' => null]);
    $this->guardian->update(['user_id' => null]);

    event(new ExamPublished($this->exam));

    $this->assertDatabaseCount('notifications', 0);
});
