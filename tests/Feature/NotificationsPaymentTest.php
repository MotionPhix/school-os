<?php

declare(strict_types=1);

use App\Domains\Finance\Events\PaymentRecorded;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
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

    $this->guardianUser = User::factory()->create(['name' => 'Guardian G']);
    makeMember($this->guardianUser, $this->tenant, []);
    $this->otherGuardianUser = User::factory()->create(['name' => 'Guardian H']);
    makeMember($this->otherGuardianUser, $this->tenant, []);

    $this->makeStudent = function (string $name): Student {
        return Student::create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'admission_number' => 'ADM-'.Str::uuid()->toString(),
            'full_name' => $name,
            'avatar_initials' => 'XX',
            'date_of_birth' => '2012-04-01',
            'stage' => 'primary',
            'grade_label' => 'Grade 5',
            'status' => 'enrolled',
        ]);
    };

    $this->linkGuardian = function (Student $student, string $guardianName, ?string $userId): void {
        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'full_name' => $guardianName,
            'avatar_initials' => 'XX',
            'user_id' => $userId,
        ]);

        DB::table('student_guardians')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'Parent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    $this->studentA = ($this->makeStudent)('Ada Lovelace');
    $this->studentB = ($this->makeStudent)('Grace Hopper');

    ($this->linkGuardian)($this->studentA, 'Grace Parent', $this->guardianUser->id);
    ($this->linkGuardian)($this->studentB, 'Alan Parent', $this->otherGuardianUser->id);

    $this->invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-'.mb_strtoupper(Str::random(8)),
        'student_id' => $this->studentA->id,
        'student_name' => $this->studentA->full_name,
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

    $this->payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $this->invoice->id,
        'invoice_number' => $this->invoice->number,
        'student_name' => $this->studentA->full_name,
        'reference' => 'PAY-'.mb_strtoupper(Str::random(8)),
        'method' => 'cash',
        'gateway' => 'manual',
        'amount_minor' => 20000,
        'gateway_fee_minor' => 0,
        'currency' => 'MWK',
        'status' => 'succeeded',
        'received_at' => now(),
    ]);
});

it('sends a payment receipt to the guardian of the invoice student only', function (): void {
    event(new PaymentRecorded($this->payment, 'entry-'.Str::uuid()->toString()));

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->guardianUser->id]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->otherGuardianUser->id]);

    $notification = Notification::query()
        ->where('notifiable_id', $this->guardianUser->id)
        ->first();

    expect($notification->data['kind'])->toBe('finance');
    expect($notification->data['title'])->toBe("Payment received — {$this->payment->reference}");
    expect($notification->data['body'])->toContain('Ada Lovelace');
    expect($notification->data['body'])->toContain('MWK 200.00');
    expect($notification->data['href'])->toBe("/finance/invoices/{$this->invoice->id}");
});

it('skips guardians without a portal account', function (): void {
    $guardianIds = DB::table('student_guardians')
        ->where('student_id', $this->studentA->id)
        ->pluck('guardian_id');

    Guardian::query()->whereIn('id', $guardianIds)->update(['user_id' => null]);

    event(new PaymentRecorded($this->payment, 'entry-'.Str::uuid()->toString()));

    $this->assertDatabaseCount('notifications', 0);
});

it('does not notify when the invoice student has no linked guardian', function (): void {
    DB::table('student_guardians')->where('student_id', $this->studentA->id)->delete();

    event(new PaymentRecorded($this->payment, 'entry-'.Str::uuid()->toString()));

    $this->assertDatabaseCount('notifications', 0);
});
