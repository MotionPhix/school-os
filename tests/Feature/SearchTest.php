<?php

declare(strict_types=1);

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Campus;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->otherTenant = makeTenant();

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

    $this->searcher = User::factory()->create(['name' => 'Searcher S']);
    makeMember($this->searcher, $this->tenant, [
        'people.students.read',
        'identity.users.read',
        'finance.invoices.read',
        'communications.announcements.read',
    ]);

    $this->studentA = Student::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'admission_number' => 'ADM-1001',
        'full_name' => 'Ada Lovelace',
        'avatar_initials' => 'AL',
        'date_of_birth' => '2012-04-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
    ]);

    $this->studentB = Student::create([
        'tenant_id' => $this->otherTenant->id,
        'campus_id' => $this->campus->id,
        'admission_number' => 'ADM-2002',
        'full_name' => 'Grace Hopper',
        'avatar_initials' => 'GH',
        'date_of_birth' => '2011-03-15',
        'stage' => 'primary',
        'grade_label' => 'Grade 6',
        'status' => 'enrolled',
    ]);

    $this->invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-1000',
        'student_id' => $this->studentA->id,
        'student_name' => 'Ada Lovelace',
        'student_initials' => 'AL',
        'grade_label' => 'Grade 5',
        'guardian_name' => 'Grace Parent',
        'academic_year_id' => $this->year->id,
        'academic_year_label' => '2026/2027',
        'term_label' => 'Term 1',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(20)->toDateString(),
        'currency' => 'MWK',
        'subtotal_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => 50000,
        'paid_minor' => 0,
        'balance_minor' => 50000,
        'status' => 'issued',
    ]);

    $this->announcement = Announcement::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Sports day moved',
        'body' => 'Sports day moves to Friday.',
        'audience' => 'whole_school',
        'audience_label' => 'Whole school',
        'channels' => ['in_app'],
        'status' => 'sent',
        'author_id' => $this->searcher->id,
        'author_name' => 'Searcher S',
        'sent_at' => now(),
        'recipient_count' => 10,
        'delivered_count' => 10,
        'read_count' => 5,
    ]);

    Sanctum::actingAs($this->searcher);
});

it('returns typed matches from every permitted resource', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/search?q=lovelace')
        ->assertStatus(200)
        ->assertJsonPath('data.students.0.label', 'Ada Lovelace')
        ->assertJsonPath('data.students.0.subtitle', 'Grade 5 · ADM-1001')
        ->assertJsonPath('data.invoices.0.label', 'INV-1000')
        ->assertJsonPath('data.invoices.0.subtitle', 'Ada Lovelace');
});

it('matches announcements by title', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/search?q=sports')
        ->assertStatus(200)
        ->assertJsonPath('data.announcements.0.label', 'Sports day moved');
});

it('never leaks records from another tenant', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/search?q=hopper')
        ->assertStatus(200)
        ->assertJsonPath('data.students', []);
});

it('omits resources the caller cannot read', function (): void {
    $limited = User::factory()->create(['name' => 'Limited L']);
    makeMember($limited, $this->tenant, ['people.students.read']);
    Sanctum::actingAs($limited);

    $response = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/search?q=lovelace')
        ->assertStatus(200)
        ->assertJsonPath('data.students.0.label', 'Ada Lovelace');

    $data = $response->json('data');
    expect($data)->toHaveKey('students');
    expect($data)->not->toHaveKey('users');
    expect($data)->not->toHaveKey('invoices');
    expect($data)->not->toHaveKey('announcements');
});

it('returns empty data for an empty query', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/search?q=')
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});

it('finds users only within the active tenant membership', function (): void {
    $foreignUser = User::factory()->create(['name' => 'Frida Khalkho']);
    makeMember($foreignUser, $this->otherTenant, []);
    $localUser = User::factory()->create(['name' => 'Ada Turing']);
    makeMember($localUser, $this->tenant, []);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/search?q=turing')
        ->assertStatus(200)
        ->assertJsonPath('data.users.0.label', 'Ada Turing');
});
