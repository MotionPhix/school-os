<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Guardian;
use App\Models\StaffMember;
use App\Models\Student;
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
});

function makeStudent(Tenant $tenant, Campus $campus, array $overrides = []): Student
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

function makeGuardian(Tenant $tenant, array $overrides = []): Guardian
{
    return Guardian::create(array_merge([
        'tenant_id' => $tenant->id,
        'full_name' => 'Grace Hopper',
        'avatar_initials' => 'GH',
    ], $overrides));
}

function makeStaff(Tenant $tenant, Campus $campus, array $overrides = []): StaffMember
{
    return StaffMember::create(array_merge([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
        'staff_number' => 'STF-'.Str::uuid()->toString(),
        'full_name' => 'Alan Turing',
        'avatar_initials' => 'AT',
        'title' => 'Teacher',
        'department' => 'Science',
        'subjects_taught' => ['Mathematics'],
        'hired_on' => '2024-01-15',
    ], $overrides));
}

describe('student authorization', function (): void {
    it('rejects creating a student without people.students.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/people/students', [
                'campus_id' => $this->campus->id,
                'admission_number' => 'ADM-2026-001',
                'full_name' => 'Ada Lovelace',
                'gender' => 'female',
                'date_of_birth' => '2012-04-01',
                'stage' => 'primary',
                'grade_label' => 'Grade 5',
            ])
            ->assertStatus(403);
    });

    it('allows creating a student with people.students.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.students.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/people/students', [
                'campus_id' => $this->campus->id,
                'admission_number' => 'ADM-2026-001',
                'full_name' => 'Ada Lovelace',
                'gender' => 'female',
                'date_of_birth' => '2012-04-01',
                'stage' => 'primary',
                'grade_label' => 'Grade 5',
            ])
            ->assertStatus(201);
    });

    it('rejects changing status without people.students.write', function (): void {
        $student = makeStudent($this->tenant, $this->campus);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.students.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/people/students/{$student->id}/status", ['status' => 'withdrawn'])
            ->assertStatus(403);
    });

    it('returns 404 for a student of another tenant', function (): void {
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
        $studentB = makeStudent($otherTenant, $campusB);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.students.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/people/students/{$studentB->id}")
            ->assertStatus(404);
    });
});

describe('guardian authorization', function (): void {
    it('rejects creating a guardian without people.guardians.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/people/guardians', ['full_name' => 'Grace Hopper'])
            ->assertStatus(403);
    });

    it('allows creating a guardian with people.guardians.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.guardians.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/people/guardians', ['full_name' => 'Grace Hopper'])
            ->assertStatus(201);
    });

    it('rejects linking a guardian without write permissions on both records', function (): void {
        $student = makeStudent($this->tenant, $this->campus);
        $guardian = makeGuardian($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.students.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson("/api/v1/people/students/{$student->id}/guardians/{$guardian->id}")
            ->assertStatus(403);
    });

    it('allows linking a guardian with write permissions on both records', function (): void {
        $student = makeStudent($this->tenant, $this->campus);
        $guardian = makeGuardian($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.students.write', 'people.guardians.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson("/api/v1/people/students/{$student->id}/guardians/{$guardian->id}", [
                'relationship' => 'Parent',
            ])
            ->assertStatus(200);
    });

    it('returns 404 for a guardian of another tenant', function (): void {
        $otherTenant = makeTenant();
        $guardianB = makeGuardian($otherTenant);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.guardians.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/people/guardians/{$guardianB->id}")
            ->assertStatus(404);
    });
});

describe('staff authorization', function (): void {
    it('rejects creating a staff member without people.staff.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/people/staff', [
                'campus_id' => $this->campus->id,
                'staff_number' => 'STF-2026-001',
                'full_name' => 'Alan Turing',
                'title' => 'Teacher',
                'department' => 'Science',
                'hired_on' => '2024-01-15',
            ])
            ->assertStatus(403);
    });

    it('allows creating a staff member with people.staff.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.staff.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/people/staff', [
                'campus_id' => $this->campus->id,
                'staff_number' => 'STF-2026-001',
                'full_name' => 'Alan Turing',
                'title' => 'Teacher',
                'department' => 'Science',
                'category' => 'teaching',
                'employment_type' => 'permanent',
                'hired_on' => '2024-01-15',
            ])
            ->assertStatus(201);
    });

    it('returns 404 for a staff member of another tenant', function (): void {
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
        $staffB = makeStaff($otherTenant, $campusB);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['people.staff.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/people/staff/{$staffB->id}")
            ->assertStatus(404);
    });
});
