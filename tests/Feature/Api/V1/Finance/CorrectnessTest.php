<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Campus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    $this->user = User::factory()->create();
    makeMember($this->user, $this->tenant, [
        'finance.invoices.read',
        'finance.invoices.write',
        'finance.invoices.issue',
        'finance.payments.write',
        'finance.ledger.read',
    ]);
    Sanctum::actingAs($this->user);

    $this->makeInvoice = function (array $overrides = [], array $lines = [['Tuition', 'tuition', 10000]]): Invoice {
        $subtotal = array_sum(array_column($lines, 2));
        $discount = (int) ($overrides['discount_minor'] ?? 0);
        $number = 'INV-'.mb_strtoupper(Str::random(8));

        $invoice = Invoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'number' => $number,
            'student_id' => $this->student->id,
            'student_name' => $this->student->full_name,
            'student_initials' => 'AL',
            'grade_label' => 'Grade 5',
            'guardian_name' => 'Grace Hopper',
            'academic_year_label' => '2026',
            'term_label' => 'Term 1',
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(20)->toDateString(),
            'currency' => 'MWK',
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'total_minor' => $subtotal - $discount,
            'paid_minor' => 0,
            'balance_minor' => $subtotal - $discount,
            'status' => InvoiceStatus::Draft,
        ], array_diff_key($overrides, ['discount_minor' => true])));

        foreach ($lines as [$description, $category, $amount]) {
            InvoiceLine::create([
                'tenant_id' => $this->tenant->id,
                'invoice_id' => $invoice->id,
                'description' => $description,
                'category' => $category,
                'quantity' => 1,
                'unit_amount_minor' => $amount,
                'amount_minor' => $amount,
                'position' => 0,
            ]);
        }

        return $invoice;
    };

    $this->issue = function (Invoice $invoice): void {
        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/issue")
            ->assertStatus(200);
    };

    $this->pay = function (Invoice $invoice, int $amount, string $method = 'cash'): TestResponse {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/payments", [
                'amount_minor' => $amount,
                'method' => $method,
            ]);
    };
});

function ledgerEntryBalance(string $table, string $column, mixed $value): array
{
    $entryId = DB::table($table)->where($column, $value)->value('id');
    $debits = (int) DB::table('finance_ledger_postings')->where('journal_entry_id', $entryId)->where('side', 'debit')->sum('amount_minor');
    $credits = (int) DB::table('finance_ledger_postings')->where('journal_entry_id', $entryId)->where('side', 'credit')->sum('amount_minor');

    return [$debits, $credits];
}

it('settles an invoice with a full payment and posts a balanced entry', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);

    $response = ($this->pay)($invoice, 10000)->assertStatus(201);
    $paymentId = $response->json('data.id');

    [$debits, $credits] = ledgerEntryBalance('finance_journal_entries', 'source_id', $paymentId);
    expect($debits)->toBe($credits)->toBe(10000);

    $row = DB::table('finance_invoices')->where('id', $invoice->id)->first();
    expect($row->status)->toBe('paid');
    expect((int) $row->paid_minor)->toBe(10000);
    expect((int) $row->balance_minor)->toBe(0);
});

it('marks a partial payment as partially paid', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);

    ($this->pay)($invoice, 4000)->assertStatus(201);

    $row = DB::table('finance_invoices')->where('id', $invoice->id)->first();
    expect($row->status)->toBe('partially_paid');
    expect((int) $row->balance_minor)->toBe(6000);
});

it('rejects overpayments and double payments', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);

    ($this->pay)($invoice, 10000)->assertStatus(201);
    ($this->pay)($invoice, 1)->assertStatus(422);
    ($this->pay)($invoice, 20000)->assertStatus(422);

    $this->assertDatabaseCount('finance_payments', 1);
});

it('computes the gateway fee with integer math and keeps the entry balanced', function (): void {
    $invoice = ($this->makeInvoice)([], [['Tuition', 'tuition', 1000000]]);
    ($this->issue)($invoice);

    // Default PayChangu bps = 25 → fee = round(1_000_000 * 25 / 10_000) = 2 500 minor
    $response = ($this->pay)($invoice, 1000000, 'paychangu_card')->assertStatus(201);
    $paymentId = $response->json('data.id');

    expect($response->json('data.gateway_fee_minor'))->toBe(2500);

    [$debits, $credits] = ledgerEntryBalance('finance_journal_entries', 'source_id', $paymentId);
    expect($debits)->toBe($credits)->toBe(1000000);
});

it('issues a discounted invoice with a balanced entry', function (): void {
    $invoice = ($this->makeInvoice)(['discount_minor' => 1000]);
    ($this->issue)($invoice);

    $entryId = DB::table('finance_journal_entries')->where('reference', "{$invoice->number} issued")->value('id');
    $debits = (int) DB::table('finance_ledger_postings')->where('journal_entry_id', $entryId)->where('side', 'debit')->sum('amount_minor');
    $credits = (int) DB::table('finance_ledger_postings')->where('journal_entry_id', $entryId)->where('side', 'credit')->sum('amount_minor');

    expect($debits)->toBe($credits)->toBe(10000);
});
