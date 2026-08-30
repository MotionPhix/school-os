<?php

declare(strict_types=1);

use App\Enums\OfferStatus;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\ApplicationOffer;
use App\Models\Campus;
use App\Models\Tenant;
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
});

function makeApplication(Tenant $tenant, Campus $campus, AcademicYear $year, array $overrides = []): Application
{
    return Application::create(array_merge([
        'tenant_id' => $tenant->id,
        'reference' => 'APP-'.mb_strtoupper(Str::random(8)),
        'applicant_full_name' => 'John Doe',
        'avatar_initials' => 'JD',
        'date_of_birth' => '2010-05-01',
        'gender' => 'male',
        'guardian_name' => 'Jane Doe',
        'campus_id' => $campus->id,
        'academic_year_id' => $year->id,
        'intended_stage' => 'primary',
        'intended_grade_label' => 'Grade 6',
        'source' => 'walk_in',
        'stage' => 'enquiry',
    ], $overrides));
}

function makeOffer(Application $application, array $overrides = []): ApplicationOffer
{
    return ApplicationOffer::create(array_merge([
        'application_id' => $application->id,
        'status' => OfferStatus::Sent,
        'fee_amount' => 50000,
        'currency_code' => 'MWK',
    ], $overrides));
}

describe('application authorization', function (): void {
    it('rejects creating an application without admissions.applications.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/admissions/applications', [
                'applicant_full_name' => 'John Doe',
                'date_of_birth' => '2010-05-01',
                'gender' => 'male',
                'guardian_name' => 'Jane Doe',
                'campus_id' => $this->campus->id,
                'academic_year_id' => $this->year->id,
                'intended_stage' => 'primary',
                'intended_grade_label' => 'Grade 6',
                'source' => 'walk_in',
            ])
            ->assertStatus(403);
    });

    it('allows creating an application with admissions.applications.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['admissions.applications.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/admissions/applications', [
                'applicant_full_name' => 'John Doe',
                'date_of_birth' => '2010-05-01',
                'gender' => 'male',
                'guardian_name' => 'Jane Doe',
                'campus_id' => $this->campus->id,
                'academic_year_id' => $this->year->id,
                'intended_stage' => 'primary',
                'intended_grade_label' => 'Grade 6',
                'source' => 'walk_in',
            ])
            ->assertStatus(201);
    });

    it('rejects advancing an application without admissions.applications.write', function (): void {
        $application = makeApplication($this->tenant, $this->campus, $this->year);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['admissions.applications.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/admissions/applications/{$application->id}/advance", [
                'to_stage' => 'application',
            ])
            ->assertStatus(403);
    });

    it('returns 404 for an application of another tenant', function (): void {
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
        $applicationB = makeApplication($otherTenant, $campusB, $yearB);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['admissions.applications.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/admissions/applications/{$applicationB->id}")
            ->assertStatus(404);
    });
});

describe('offer response audit integrity', function (): void {
    it('records the authenticated user as the actor, ignoring client-supplied actor_name', function (): void {
        $application = makeApplication($this->tenant, $this->campus, $this->year);
        makeOffer($application);

        $user = User::factory()->create(['name' => 'Registrar One']);
        makeMember($user, $this->tenant, ['admissions.offers.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/admissions/applications/{$application->id}/offer/response", [
                'response' => 'accepted',
                'actor_name' => 'Evil Hacker', // must be ignored
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('application_stage_events', [
            'application_id' => $application->id,
            'actor_id' => $user->id,
            'actor_name' => 'Registrar One',
        ]);

        $this->assertDatabaseMissing('application_stage_events', [
            'application_id' => $application->id,
            'actor_name' => 'Evil Hacker',
        ]);
    });
});
