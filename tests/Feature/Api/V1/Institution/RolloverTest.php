<?php

declare(strict_types=1);

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Track 4 — academic-year rollover guards. The transition machine is
 * enforced in TransitionAcademicYear; these tests pin the boundary rules:
 * date-overlap conflicts, open-term blocks on close, supervised reopen,
 * and single-current-year integrity.
 */
beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->user = User::factory()->create();
    makeMember($this->user, $this->tenant, ['institution.years.write']);
    Sanctum::actingAs($this->user);

    $this->makeYear = function (string $label, string $from, string $to, string $status = 'planning', bool $current = false): AcademicYear {
        return AcademicYear::create([
            'tenant_id' => $this->tenant->id,
            'label' => $label,
            'starts_on' => $from,
            'ends_on' => $to,
            'status' => $status,
            'is_current' => $current,
        ]);
    };

    $this->transition = function (AcademicYear $year, string $status) {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/institution/academic-years/{$year->id}/transition", ['status' => $status]);
    };

    $this->setCurrent = function (AcademicYear $year) {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/institution/academic-years/{$year->id}/set-current");
    };
});

it('activates a planning year and rejects date-overlapping actives', function (): void {
    $a = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31');
    $b = ($this->makeYear)('2025/2026', '2026-01-01', '2026-12-31'); // overlaps A

    ($this->transition)($a, 'active')->assertOk();
    ($this->transition)($b, 'active')->assertStatus(422);

    expect($a->fresh()->status)->toBe(AcademicYearStatus::Active)
        ->and($b->fresh()->status)->toBe(AcademicYearStatus::Planning);
});

it('activates a non-overlapping year even while another is active', function (): void {
    $a = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31');
    $b = ($this->makeYear)('2027/2028', '2027-09-01', '2028-08-31');

    ($this->transition)($a, 'active')->assertOk();
    ($this->transition)($b, 'active')->assertOk();

    expect(AcademicYear::query()->where('status', 'active')->count())->toBe(2);
});

it('rejects illegal transitions outright', function (): void {
    $year = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31');

    // Planning → Closed is not a legal move.
    ($this->transition)($year, 'closed')->assertStatus(422);

    // Active → Planning is not legal either.
    ($this->transition)($year, 'active')->assertOk();
    ($this->transition)($year->fresh(), 'planning')->assertStatus(422);
});

it('blocks closing a year while any term is still open', function (): void {
    $year = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31');
    ($this->transition)($year, 'active')->assertOk();

    Term::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $year->id,
        'name' => 'Term 1',
        'sequence' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'status' => 'in_progress',
    ]);

    ($this->transition)($year->fresh(), 'closed')->assertStatus(422);
    expect($year->fresh()->status)->toBe(AcademicYearStatus::Active);
});

it('closes a year once all terms are completed and unflags it as current', function (): void {
    $year = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31', 'active', true);
    Term::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $year->id,
        'name' => 'Term 1',
        'sequence' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'status' => 'completed',
    ]);

    ($this->transition)($year, 'closed')->assertOk();

    expect($year->fresh()->status)->toBe(AcademicYearStatus::Closed)
        ->and($year->fresh()->is_current)->toBeFalse();
});

it('allows a supervised reopen from closed back to active', function (): void {
    $year = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31', 'closed');

    ($this->transition)($year, 'active')->assertOk();

    expect($year->fresh()->status)->toBe(AcademicYearStatus::Active);
});

it('keeps exactly one current year per tenant', function (): void {
    $a = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31', 'active', true);
    $b = ($this->makeYear)('2025/2026', '2025-09-01', '2026-08-31', 'planning');

    ($this->setCurrent)($b)->assertOk();

    expect(AcademicYear::query()->where('tenant_id', $this->tenant->id)->where('is_current', true)->count())->toBe(1)
        ->and($b->fresh()->is_current)->toBeTrue()
        ->and($a->fresh()->is_current)->toBeFalse();
});

it('refuses to set a closed year as current', function (): void {
    $year = ($this->makeYear)('2026/2027', '2026-09-01', '2027-08-31', 'closed');

    ($this->setCurrent)($year)->assertStatus(422);

    expect($year->fresh()->is_current)->toBeFalse();
});
