<?php

declare(strict_types=1);

use App\Domains\Finance\Services\IssueInvoice;
use App\Domains\Finance\Services\RefundPayment;
use App\Domains\Finance\Services\VoidInvoice;
use App\Domains\Finance\Services\WriteInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Campus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Track 3 — finance hardening: row-lock races (issue/void/refund) and
 * money-math edge cases. The payment path was already locked; these tests
 * pin the same fail-closed invariants on the remaining finance verbs.
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
        'finance.invoices.void',
        'finance.payments.write',
        'finance.payments.refund',
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
            'total_minor' => max(0, $subtotal - $discount),
            'paid_minor' => 0,
            'balance_minor' => max(0, $subtotal - $discount),
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

    $this->issue = function (Invoice $invoice): TestResponse {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/issue");
    };

    $this->pay = function (Invoice $invoice, int $amount, string $method = 'cash'): TestResponse {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/payments", [
                'amount_minor' => $amount,
                'method' => $method,
            ]);
    };

    $this->refund = function (Payment $payment): TestResponse {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/payments/{$payment->id}/refund", ['reason' => 'Test refund']);
    };

    $this->void = function (Invoice $invoice): TestResponse {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/finance/invoices/{$invoice->id}/void", ['reason' => 'Test void']);
    };
});

function financeEntrySides(string $entryId): array
{
    $debits = (int) DB::table('finance_ledger_postings')->where('journal_entry_id', $entryId)->where('side', 'debit')->sum('amount_minor');
    $credits = (int) DB::table('finance_ledger_postings')->where('journal_entry_id', $entryId)->where('side', 'credit')->sum('amount_minor');

    return [$debits, $credits];
}

function financePostings(string $entryId): array
{
    return DB::table('finance_ledger_postings')
        ->where('journal_entry_id', $entryId)
        ->orderBy('amount_minor')
        ->orderBy('side')
        ->get(['side', 'amount_minor'])
        ->map(fn ($row): array => [$row->side, (int) $row->amount_minor])
        ->all();
}

it('prevents a second issue of the same invoice (double-posting race)', function (): void {
    $invoice = ($this->makeInvoice)();

    ($this->issue)($invoice)->assertStatus(200);

    // The policy layer denies a repeat issue with 403 …
    ($this->issue)($invoice)->assertStatus(403);

    // … and the service re-checks the locked row, so a bypass still fails closed.
    expect(fn () => app(IssueInvoice::class)->handle($invoice, $this->user))
        ->toThrow(ValidationException::class);

    $entries = DB::table('finance_journal_entries')
        ->where('source_type', 'invoice')
        ->where('source_id', $invoice->id)
        ->where('is_reversal', false)
        ->count();
    expect($entries)->toBe(1);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);
});

it('refuses to void an invoice that still has succeeded payments', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);
    ($this->pay)($invoice, 4000)->assertStatus(201);

    ($this->void)($invoice)->assertStatus(422);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
    expect(DB::table('finance_journal_entries')->where('is_reversal', true)->count())->toBe(0);
});

it('refunds a partial payment, restores the balance and posts a mirror reversal', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);
    $paymentId = ($this->pay)($invoice, 4000)->assertStatus(201)->json('data.id');

    ($this->refund)($invoice->payments()->findOrFail($paymentId))->assertStatus(200);

    $row = DB::table('finance_invoices')->where('id', $invoice->id)->first();
    expect((int) $row->paid_minor)->toBe(0);
    expect((int) $row->balance_minor)->toBe(10000);
    expect($row->status)->toBe('issued');

    $original = DB::table('finance_journal_entries')
        ->where('source_type', 'payment')->where('source_id', $paymentId)->where('is_reversal', false)->firstOrFail();
    $reversal = DB::table('finance_journal_entries')
        ->where('source_type', 'payment')->where('source_id', $paymentId)->where('is_reversal', true)->firstOrFail();

    [$d1, $c1] = financeEntrySides($original->id);
    [$d2, $c2] = financeEntrySides($reversal->id);
    expect($d1)->toBe($c1)->toBe(4000);
    expect($d2)->toBe($c2)->toBe(4000);

    // Reversal mirrors the original with sides flipped.
    expect(financePostings($reversal->id))->toBe([
        ['credit', 4000],
        ['debit', 4000],
    ]);
    expect((bool) $reversal->reverses_entry_id)->toBeTrue();
});

it('rejects a second refund of the same payment (double-refund race)', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);
    $paymentId = ($this->pay)($invoice, 10000)->assertStatus(201)->json('data.id');
    $payment = $invoice->payments()->findOrFail($paymentId);

    ($this->refund)($payment)->assertStatus(200);

    // Policy denies a repeat refund with 403; the service re-checks too.
    ($this->refund)($payment)->assertStatus(403);
    expect(fn () => app(RefundPayment::class)->handle($payment, $this->user))
        ->toThrow(ValidationException::class);

    expect(DB::table('finance_journal_entries')->where('is_reversal', true)->count())->toBe(1);
});

it('voids a fully refunded invoice once via the API, idempotently at the service layer', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);
    $paymentId = ($this->pay)($invoice, 10000)->assertStatus(201)->json('data.id');

    ($this->refund)($invoice->payments()->findOrFail($paymentId))->assertStatus(200);
    ($this->void)($invoice)->assertStatus(200);

    // The policy layer denies voiding an already-voided invoice (403)…
    ($this->void)($invoice)->assertStatus(403);

    // … while the service stays idempotent for internal callers.
    $voided = app(VoidInvoice::class)->handle($invoice->fresh(), $this->user);
    expect($voided->status)->toBe(InvoiceStatus::Void);

    // Exactly two reversals: one for the payment, one for the issue.
    $reversals = DB::table('finance_journal_entries')->where('is_reversal', true)->count();
    expect($reversals)->toBe(2);

    $issueReversal = DB::table('finance_journal_entries')
        ->where('source_type', 'invoice')->where('source_id', $invoice->id)->where('is_reversal', true)->firstOrFail();
    [$d, $c] = financeEntrySides($issueReversal->id);
    expect($d)->toBe($c)->toBe(10000);
});

it('rejects issuing an invoice whose discount wipes out the total', function (): void {
    $invoice = ($this->makeInvoice)(['discount_minor' => 10000]);

    ($this->issue)($invoice)->assertStatus(422);

    expect(DB::table('finance_journal_entries')->where('source_id', $invoice->id)->count())->toBe(0);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('clamps negative totals and balances to zero in recomputeTotals', function (): void {
    $invoice = ($this->makeInvoice)(['discount_minor' => 15000]); // discount > subtotal

    app(WriteInvoice::class)->recomputeTotals($invoice);

    $row = DB::table('finance_invoices')->where('id', $invoice->id)->first();
    expect((int) $row->total_minor)->toBe(0);
    expect((int) $row->balance_minor)->toBe(0);
});

it('marks an unpaid past-due invoice overdue when totals are recomputed', function (): void {
    $invoice = ($this->makeInvoice)(['due_on' => now()->subDays(3)->toDateString()]);
    ($this->issue)($invoice);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);

    app(WriteInvoice::class)->recomputeTotals($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('refunds a gateway-fee payment with a balanced three-sided reversal', function (): void {
    $invoice = ($this->makeInvoice)([], [['Tuition', 'tuition', 1000000]]);
    ($this->issue)($invoice);
    $paymentId = ($this->pay)($invoice, 1000000, 'paychangu_card')->assertStatus(201)->json('data.id');

    ($this->refund)($invoice->payments()->findOrFail($paymentId))->assertStatus(200);

    $reversal = DB::table('finance_journal_entries')
        ->where('source_type', 'payment')->where('source_id', $paymentId)->where('is_reversal', true)->firstOrFail();

    [$d, $c] = financeEntrySides($reversal->id);
    expect($d)->toBe($c)->toBe(1000000);

    // Original had 3 postings (cash 997500, fees 2500, AR 1000000); the
    // reversal mirrors all three with sides flipped.
    expect(count(financePostings($reversal->id)))->toBe(3);

    $row = DB::table('finance_invoices')->where('id', $invoice->id)->first();
    expect((int) $row->paid_minor)->toBe(0);
    expect((int) $row->balance_minor)->toBe(1000000);
});

it('rejects a payment on a voided invoice', function (): void {
    $invoice = ($this->makeInvoice)();
    ($this->issue)($invoice);
    $paymentId = ($this->pay)($invoice, 10000)->assertStatus(201)->json('data.id');

    ($this->refund)($invoice->payments()->findOrFail($paymentId))->assertStatus(200);
    ($this->void)($invoice)->assertStatus(200);

    ($this->pay)($invoice, 1)->assertStatus(422);
    expect(DB::table('finance_payments')->count())->toBe(1);
});
