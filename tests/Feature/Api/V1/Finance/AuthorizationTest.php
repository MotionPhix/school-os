<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Campus;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\PaymentPolicy;
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
});

function makeInvoice(Tenant $tenant, Student $student, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'tenant_id' => $tenant->id,
        'number' => 'INV-'.mb_strtoupper(Str::random(8)),
        'student_id' => $student->id,
        'student_name' => $student->full_name,
        'student_initials' => 'AL',
        'grade_label' => 'Grade 5',
        'guardian_name' => 'Grace Hopper',
        'academic_year_label' => '2026',
        'term_label' => 'Term 1',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(20)->toDateString(),
        'currency' => 'MWK',
        'subtotal_minor' => 10000,
        'discount_minor' => 0,
        'total_minor' => 10000,
        'paid_minor' => 0,
        'balance_minor' => 10000,
        'status' => InvoiceStatus::Draft,
    ], $overrides));
}

function makeFee(Tenant $tenant, array $overrides = []): FeeStructure
{
    return FeeStructure::create(array_merge([
        'tenant_id' => $tenant->id,
        'academic_year_label' => '2026',
        'grade_label' => 'Grade 5',
        'name' => 'Tuition',
        'category' => 'tuition',
        'cycle' => 'term',
        'amount_minor' => 50000,
        'currency' => 'MWK',
        'is_active' => true,
    ], $overrides));
}

describe('fee structure authorization', function (): void {
    it('rejects creating a fee structure without finance.fees.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/finance/fees', [
                'grade_label' => 'Grade 5',
                'name' => 'Tuition',
                'category' => 'tuition',
                'cycle' => 'term',
                'amount_minor' => 50000,
            ])
            ->assertStatus(403);
    });

    it('allows creating a fee structure with finance.fees.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.fees.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/finance/fees', [
                'academic_year_label' => '2026',
                'grade_label' => 'Grade 5',
                'name' => 'Tuition',
                'category' => 'tuition',
                'cycle' => 'term',
                'amount_minor' => 50000,
            ])
            ->assertStatus(201);
    });

    it('rejects bulk fee actions without finance.fees.write', function (): void {
        $fee = makeFee($this->tenant);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/finance/fees/bulk', [
                'ids' => [$fee->id],
                'action' => 'activate',
            ])
            ->assertStatus(403);
    });

    it('allows bulk fee activation with finance.fees.write', function (): void {
        $fee = makeFee($this->tenant, ['is_active' => false]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.fees.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/finance/fees/bulk', [
                'ids' => [$fee->id],
                'action' => 'activate',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.affected', 1);

        $this->assertDatabaseHas('finance_fee_structures', ['id' => $fee->id, 'is_active' => true]);
    });
});

describe('invoice authorization', function (): void {
    it('rejects bulk issuing invoices without the dedicated finance.invoices.issue key', function (): void {
        $invoice = makeInvoice($this->tenant, $this->student);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.invoices.write']); // write, but NOT issue
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/finance/invoices/bulk', [
                'ids' => [$invoice->id],
                'action' => 'issue',
            ])
            ->assertStatus(403);
    });

    it('allows bulk reminding invoices with finance.invoices.write', function (): void {
        $invoice = makeInvoice($this->tenant, $this->student, ['status' => InvoiceStatus::Issued]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.invoices.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/finance/invoices/bulk', [
                'ids' => [$invoice->id],
                'action' => 'remind',
            ])
            ->assertStatus(200);
    });

    it('allows reminding an issued invoice with finance.invoices.write', function (): void {
        $invoice = makeInvoice($this->tenant, $this->student, ['status' => InvoiceStatus::Issued]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.invoices.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/remind")
            ->assertStatus(200);
    });

    it('rejects reminding an invoice without finance.invoices.write', function (): void {
        $invoice = makeInvoice($this->tenant, $this->student, ['status' => InvoiceStatus::Issued]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/remind")
            ->assertStatus(403);
    });

    it('enforces the remind policy on status', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.invoices.write']);

        $policy = new InvoicePolicy;

        expect($policy->remind($user, new Invoice(['status' => InvoiceStatus::Issued])))->toBeTrue()
            ->and($policy->remind($user, new Invoice(['status' => InvoiceStatus::PartiallyPaid])))->toBeTrue()
            ->and($policy->remind($user, new Invoice(['status' => InvoiceStatus::Void])))->toBeFalse();
    });
});

describe('payment authorization', function (): void {
    it('rejects recording a payment without finance.payments.write', function (): void {
        $invoice = makeInvoice($this->tenant, $this->student, ['status' => InvoiceStatus::Issued]);
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.invoices.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/payments", [
                'amount_minor' => 1000,
                'method' => 'cash',
            ])
            ->assertStatus(403);
    });

    it('enforces the refund policy on payment status', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['finance.payments.refund']);

        $policy = new PaymentPolicy;

        expect($policy->refund($user, new Payment(['status' => PaymentStatus::Succeeded])))->toBeTrue()
            ->and($policy->refund($user, new Payment(['status' => PaymentStatus::Refunded])))->toBeFalse()
            ->and($policy->refund($user, new Payment(['status' => PaymentStatus::Pending])))->toBeFalse();
    });
});
