<?php

declare(strict_types=1);

use App\Enums\AcademicYearStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\Campus;
use App\Models\Tenant;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);
});

function makeCampus(Tenant $tenant, array $overrides = []): Campus
{
    return Campus::create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Main Campus',
        'code' => 'MAIN',
        'status' => 'operational',
        'address_line' => '1 Test Road',
        'city' => 'Lilongwe',
        'region' => 'Central',
        'timezone' => 'Africa/Blantyre',
    ], $overrides));
}

function makeYear(Tenant $tenant, array $overrides = []): AcademicYear
{
    return AcademicYear::create(array_merge([
        'tenant_id' => $tenant->id,
        'label' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
        'status' => AcademicYearStatus::Planning,
        'is_current' => false,
    ], $overrides));
}

function makeTerm(Tenant $tenant, AcademicYear $year, array $overrides = []): Term
{
    return Term::create(array_merge([
        'tenant_id' => $tenant->id,
        'academic_year_id' => $year->id,
        'name' => 'Term 1',
        'sequence' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'status' => TermStatus::Upcoming,
    ], $overrides));
}

function makeCalendarEvent(Tenant $tenant, AcademicYear $year, array $overrides = []): CalendarEvent
{
    return CalendarEvent::create(array_merge([
        'tenant_id' => $tenant->id,
        'academic_year_id' => $year->id,
        'title' => 'Sports Day',
        'kind' => 'event',
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-01',
        'all_day' => true,
        'audience' => 'whole_school',
    ], $overrides));
}

describe('campus authorization', function (): void {
    it('rejects creating a campus without institution.campuses.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/institution/campuses', [
                'name' => 'North Campus',
                'code' => 'NORTH',
                'address_line' => '2 Test Road',
                'city' => 'Lilongwe',
                'region' => 'Central',
                'timezone' => 'Africa/Blantyre',
            ])
            ->assertStatus(403);
    });

    it('allows creating a campus with institution.campuses.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.campuses.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/institution/campuses', [
                'name' => 'North Campus',
                'code' => 'NORTH',
                'address_line' => '2 Test Road',
                'city' => 'Lilongwe',
                'region' => 'Central',
                'timezone' => 'Africa/Blantyre',
            ])
            ->assertStatus(201);
    });

    it('rejects deleting the primary campus', function (): void {
        $primary = makeCampus($this->tenant, ['is_primary' => true]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.campuses.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/v1/institution/campuses/{$primary->id}")
            ->assertStatus(403);
    });

    it('returns 404 for a campus of another tenant', function (): void {
        $otherTenant = makeTenant();
        $campusB = makeCampus($otherTenant);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.campuses.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/institution/campuses/{$campusB->id}")
            ->assertStatus(404);
    });
});

describe('academic year authorization', function (): void {
    it('rejects creating an academic year without institution.years.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/institution/academic-years', [
                'label' => '2027/2028',
                'starts_on' => '2027-09-01',
                'ends_on' => '2028-08-31',
                'status' => 'planning',
            ])
            ->assertStatus(403);
    });

    it('allows creating an academic year with institution.years.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.years.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/institution/academic-years', [
                'label' => '2027/2028',
                'starts_on' => '2027-09-01',
                'ends_on' => '2028-08-31',
                'status' => 'planning',
            ])
            ->assertStatus(201);
    });

    it('rejects deleting a year that has already run', function (): void {
        $active = makeYear($this->tenant, ['status' => AcademicYearStatus::Active]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.years.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/v1/institution/academic-years/{$active->id}")
            ->assertStatus(403);
    });

    it('returns 404 for an academic year of another tenant', function (): void {
        $otherTenant = makeTenant();
        $yearB = makeYear($otherTenant);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.years.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/institution/academic-years/{$yearB->id}")
            ->assertStatus(404);
    });
});

describe('term authorization', function (): void {
    it('rejects creating a term without institution.years.write', function (): void {
        $year = makeYear($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/institution/academic-years/{$year->id}/terms", [
                'name' => 'Term 1',
                'sequence' => 1,
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-15',
                'status' => 'upcoming',
            ])
            ->assertStatus(403);
    });

    it('allows creating a term with institution.years.write', function (): void {
        $year = makeYear($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.years.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/institution/academic-years/{$year->id}/terms", [
                'name' => 'Term 1',
                'sequence' => 1,
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-15',
                'status' => 'upcoming',
            ])
            ->assertStatus(201);
    });

    it('rejects deleting a term that is in progress', function (): void {
        $year = makeYear($this->tenant);
        $term = makeTerm($this->tenant, $year, ['status' => TermStatus::InProgress]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.years.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/v1/institution/academic-years/{$year->id}/terms/{$term->id}")
            ->assertStatus(403);
    });
});

describe('calendar authorization', function (): void {
    it('rejects creating a calendar event without institution.calendar.write', function (): void {
        $year = makeYear($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/institution/calendar', [
                'academic_year_id' => $year->id,
                'title' => 'Sports Day',
                'kind' => 'event',
                'starts_on' => '2026-10-01',
                'ends_on' => '2026-10-01',
                'audience' => 'whole_school',
            ])
            ->assertStatus(403);
    });

    it('allows creating a calendar event with institution.calendar.write', function (): void {
        $year = makeYear($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.calendar.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/institution/calendar', [
                'academic_year_id' => $year->id,
                'title' => 'Sports Day',
                'kind' => 'event',
                'starts_on' => '2026-10-01',
                'ends_on' => '2026-10-01',
                'audience' => 'whole_school',
            ])
            ->assertStatus(201);
    });

    it('returns 404 for a calendar event of another tenant', function (): void {
        $otherTenant = makeTenant();
        $yearB = makeYear($otherTenant);
        $eventB = makeCalendarEvent($otherTenant, $yearB);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.calendar.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/institution/calendar/{$eventB->id}")
            ->assertStatus(404);
    });
});

describe('institution profile authorization', function (): void {
    it('rejects updating the profile without institution.profile.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson('/api/v1/institution/profile', ['name' => 'Hijacked'])
            ->assertStatus(403);
    });

    it('allows updating the profile with institution.profile.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['institution.profile.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson('/api/v1/institution/profile', [
                'name' => 'Test School',
                'short_name' => 'TS',
                'established_year' => 1990,
                'type' => 'secondary',
                'accreditation_status' => 'pending',
                'student_capacity' => 500,
                'languages_of_instruction' => ['English'],
                'contact_email' => 'admin@school.test',
                'contact_phone' => '+265000000000',
                'address_line' => '1 Test Road',
                'city' => 'Lilongwe',
                'region' => 'Central',
            ])
            ->assertStatus(200);
    });
});
