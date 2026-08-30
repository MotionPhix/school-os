<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    $this->principal = User::factory()->create(['name' => 'Principal P']);
    makeMember($this->principal, $this->tenant, ['platform.trash.restore']);

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

    $this->makeStudent = function (string $tenantId): Student {
        return Student::create([
            'tenant_id' => $tenantId,
            'campus_id' => $this->campus->id,
            'admission_number' => 'ADM-'.Str::uuid()->toString(),
            'full_name' => 'Ada Lovelace',
            'avatar_initials' => 'AL',
            'date_of_birth' => '2012-04-01',
            'stage' => 'primary',
            'grade_label' => 'Grade 5',
            'status' => 'enrolled',
        ]);
    };

    $this->student = ($this->makeStudent)($this->tenant->id);
});

it('restores an archived record within the active tenant', function (): void {
    $this->student->delete();
    $this->assertSoftDeleted('students', ['id' => $this->student->id]);

    Sanctum::actingAs($this->principal);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/admin/trash/students/{$this->student->id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.restored', true)
        ->assertJsonPath('data.resource', 'students')
        ->assertJsonPath('data.id', $this->student->id);

    $this->assertNotSoftDeleted('students', ['id' => $this->student->id]);
});

it('denies users without the trash restore permission', function (): void {
    $this->student->delete();

    $staff = User::factory()->create(['name' => 'Staff S']);
    makeMember($staff, $this->tenant, []);
    Sanctum::actingAs($staff);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/admin/trash/students/{$this->student->id}/restore")
        ->assertStatus(403);

    $this->assertSoftDeleted('students', ['id' => $this->student->id]);
});

it('does not restore records from another tenant', function (): void {
    $otherTenant = makeTenant();
    $otherCampus = Campus::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Campus',
        'code' => 'OTHER',
        'status' => 'operational',
        'address_line' => '9 Other Road',
        'city' => 'Blantyre',
        'region' => 'South',
        'timezone' => 'Africa/Blantyre',
    ]);
    $otherStudent = Student::create([
        'tenant_id' => $otherTenant->id,
        'campus_id' => $otherCampus->id,
        'admission_number' => 'ADM-'.Str::uuid()->toString(),
        'full_name' => 'Grace Hopper',
        'avatar_initials' => 'GH',
        'date_of_birth' => '2011-03-15',
        'stage' => 'primary',
        'grade_label' => 'Grade 6',
        'status' => 'enrolled',
    ]);
    $otherStudent->delete();

    Sanctum::actingAs($this->principal);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/admin/trash/students/{$otherStudent->id}/restore")
        ->assertStatus(404);

    $this->assertSoftDeleted('students', ['id' => $otherStudent->id]);
});

it('rejects unknown trash resources', function (): void {
    $this->student->delete();

    Sanctum::actingAs($this->principal);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/admin/trash/black_holes/{$this->student->id}/restore")
        ->assertStatus(404);
});

it('rejects records that are not archived', function (): void {
    Sanctum::actingAs($this->principal);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/admin/trash/students/{$this->student->id}/restore")
        ->assertStatus(404);
});
