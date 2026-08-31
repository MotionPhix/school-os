<?php

declare(strict_types=1);

use App\Domains\Assessments\Services\BuildTermReportCards;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamStatus;
use App\Enums\GradeBand;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\ExamResult;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Track 4 — marksheet (report-card) boundaries and completeness.
 * BuildTermReportCards only folds in published exams with scores; the
 * band mapping is pinned at every boundary (75/65/55/45/40) and the
 * overall average is the mean of per-subject averages.
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
        'status' => 'active',
        'is_current' => true,
    ]);

    $this->term = Term::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'name' => 'Term 1',
        'sequence' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'status' => 'in_progress',
    ]);

    $this->staff = StaffMember::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'staff_number' => 'STF-'.Str::uuid()->toString(),
        'full_name' => 'Alan Turing',
        'avatar_initials' => 'AT',
        'title' => 'Teacher',
        'department' => 'Science',
        'subjects_taught' => ['Mathematics', 'Science'],
        'hired_on' => '2024-01-15',
    ]);

    $this->makeSubject = function (string $code, string $name): Subject {
        return Subject::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'category' => 'core',
            'stages' => ['primary'],
            'is_core' => false,
            'credit_hours' => 4,
        ]);
    };

    $this->makeSection = function (Subject $subject): CourseSection {
        return CourseSection::create([
            'tenant_id' => $this->tenant->id,
            'academic_year_id' => $this->year->id,
            'campus_id' => $this->campus->id,
            'subject_id' => $subject->id,
            'grade_label' => 'Grade 5',
            'section_label' => strtoupper(Str::random(3)),
            'teacher_id' => $this->staff->id,
            'capacity' => 32,
            'status' => 'draft',
        ]);
    };

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

    $this->makePeriod = function (): ExamPeriod {
        return ExamPeriod::create([
            'tenant_id' => $this->tenant->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'name' => 'Mid-year',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-15',
            'status' => ExamPeriodStatus::Closed,
        ]);
    };

    $this->makeExam = function (CourseSection $section, ExamPeriod $period, int $max, string $status = ExamStatus::Published->value): Exam {
        return Exam::create([
            'tenant_id' => $this->tenant->id,
            'period_id' => $period->id,
            'course_section_id' => $section->id,
            'paper_title' => 'Test paper',
            'scheduled_on' => '2026-10-10',
            'starts_at' => '09:00',
            'duration_minutes' => 60,
            'max_score' => $max,
            'pass_mark' => (int) round($max * 0.4),
            'status' => $status,
        ]);
    };

    $this->mark = function (Exam $exam, Student $student, ?int $score): void {
        ExamResult::create([
            'tenant_id' => $this->tenant->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'score' => $score,
            'band' => $score === null || $exam->max_score <= 0
                ? null
                : GradeBand::forTotal((int) round($score / $exam->max_score * 100)),
        ]);
    };

    $this->cardFor = function (array $cards, string $studentName): array {
        foreach ($cards as $card) {
            if ($card['student_name'] === $studentName) {
                return $card;
            }
        }

        throw new RuntimeException("No card for {$studentName}");
    };
});

it('derives the correct band at every boundary', function (): void {
    $section = ($this->makeSection)(($this->makeSubject)('MATH', 'Mathematics'));
    $period = ($this->makePeriod)();

    foreach ([75 => 'A', 74 => 'B', 65 => 'B', 64 => 'C', 55 => 'C', 54 => 'D', 45 => 'D', 44 => 'E', 40 => 'E', 39 => 'F'] as $score => $band) {
        $exam = ($this->makeExam)($section, $period, 100);
        $student = ($this->makeStudent)("Student {$score}");
        ($this->mark)($exam, $student, $score);
    }

    $cards = app(BuildTermReportCards::class)->handle($this->term);
    $byName = collect($cards)->keyBy('student_name');

    foreach ([75 => 'A', 74 => 'B', 65 => 'B', 64 => 'C', 55 => 'C', 54 => 'D', 45 => 'D', 44 => 'E', 40 => 'E', 39 => 'F'] as $score => $band) {
        expect($byName["Student {$score}"]['overall_band'])->toBe($band)
            ->and($byName["Student {$score}"]['lines'][0]['best_band'])->toBe($band);
    }
});

it('averages exams per subject and subjects into the overall', function (): void {
    $math = ($this->makeSubject)('MATH', 'Mathematics');
    $sci = ($this->makeSubject)('SCI', 'Science');
    $mathSection = ($this->makeSection)($math);
    $sciSection = ($this->makeSection)($sci);
    $period = ($this->makePeriod)();

    $grace = ($this->makeStudent)('Grace Hopper');

    // Two exams in MATH: 60% + 80% → subject average 70 → B.
    ($this->mark)(($this->makeExam)($mathSection, $period, 100), $grace, 60);
    ($this->mark)(($this->makeExam)($mathSection, $period, 100), $grace, 80);
    // One exam in SCI: 90 → A.
    ($this->mark)(($this->makeExam)($sciSection, $period, 100), $grace, 90);

    $cards = app(BuildTermReportCards::class)->handle($this->term);
    $card = ($this->cardFor)($cards, 'Grace Hopper');

    expect($card['lines'])->toHaveCount(2);
    $lines = collect($card['lines'])->keyBy('subject_code');
    expect($lines['MATH']['average'])->toBe(70.0)
        ->and($lines['MATH']['exams_count'])->toBe(2)
        ->and($lines['MATH']['best_band'])->toBe('A') // best exam was 80 → A
        ->and($lines['SCI']['average'])->toBe(90.0)
        ->and($lines['SCI']['best_band'])->toBe('A');

    // Overall = mean of subject averages (70 + 90) / 2 = 80 → A (≥ 75).
    expect($card['overall_average'])->toBe(80.0)
        ->and($card['overall_band'])->toBe('A');
});

it('keeps a subject line per section even when another subject has no results', function (): void {
    $math = ($this->makeSubject)('MATH', 'Mathematics');
    $sci = ($this->makeSubject)('SCI', 'Science');
    $mathSection = ($this->makeSection)($math);
    $sciSection = ($this->makeSection)($sci);
    $period = ($this->makePeriod)();

    $ada = ($this->makeStudent)('Ada Lovelace');
    // Ada has a MATH result only; the published SCI exam has no result for her.
    ($this->mark)(($this->makeExam)($mathSection, $period, 100), $ada, 80);
    ($this->makeExam)($sciSection, $period, 100);

    $cards = app(BuildTermReportCards::class)->handle($this->term);
    $card = ($this->cardFor)($cards, 'Ada Lovelace');

    expect($card['lines'])->toHaveCount(1)
        ->and($card['lines'][0]['subject_code'])->toBe('MATH')
        ->and($card['overall_average'])->toBe(80.0)
        ->and($card['overall_band'])->toBe('A'); // 80 ≥ 75
});

it('excludes draft and marking exams from the rollup', function (): void {
    $section = ($this->makeSection)(($this->makeSubject)('MATH', 'Mathematics'));
    $period = ($this->makePeriod)();

    $ada = ($this->makeStudent)('Ada Lovelace');
    ($this->mark)(($this->makeExam)($section, $period, 100, ExamStatus::Published->value), $ada, 90);
    ($this->mark)(($this->makeExam)($section, $period, 100, ExamStatus::Draft->value), $ada, 20);
    ($this->mark)(($this->makeExam)($section, $period, 100, ExamStatus::Marking->value), $ada, 30);

    $cards = app(BuildTermReportCards::class)->handle($this->term);
    $card = ($this->cardFor)($cards, 'Ada Lovelace');

    expect($card['lines'][0]['exams_count'])->toBe(1)
        ->and($card['lines'][0]['average'])->toBe(90.0);
});

it('guards against a zero max score without dividing by zero', function (): void {
    $section = ($this->makeSection)(($this->makeSubject)('MATH', 'Mathematics'));
    $period = ($this->makePeriod)();

    $ada = ($this->makeStudent)('Ada Lovelace');
    $exam = ($this->makeExam)($section, $period, 0);
    ($this->mark)($exam, $ada, 0);

    $cards = app(BuildTermReportCards::class)->handle($this->term);
    $card = ($this->cardFor)($cards, 'Ada Lovelace');

    expect($card['lines'][0]['average'])->toBe(0.0)
        ->and($card['overall_band'])->toBe('F');
});

it('returns an empty list for a term with no published results', function (): void {
    $section = ($this->makeSection)(($this->makeSubject)('MATH', 'Mathematics'));
    $period = ($this->makePeriod)();
    $exam = ($this->makeExam)($section, $period, 100);
    $ada = ($this->makeStudent)('Ada Lovelace');
    ($this->mark)($exam, $ada, null); // unmarked — score is null

    $cards = app(BuildTermReportCards::class)->handle($this->term);
    expect($cards)->toBe([]);
});
