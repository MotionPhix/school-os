<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\InvoiceDrafted;
use App\Domains\Finance\Events\InvoiceUpdated;
use App\Domains\Finance\Support\InvoiceNumberGenerator;
use App\Enums\CurrencyCode;
use App\Enums\FeeCategory;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\Term;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a *draft* invoice. Nothing hits the ledger here —
 * IssueInvoice does that. Posted invoices reject line edits; the only
 * way to change them is to void and re-issue.
 *
 * Line writes are all-or-nothing: pass the full list, we replace.
 */
final class WriteInvoice
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * @param array{
     *   student_id:string,
     *   term_id?:?string,
     *   issued_on?:string,
     *   due_on?:string,
     *   currency?:string,
     *   discount_minor?:int,
     *   lines: list<array{fee_structure_id?:?string,description:string,category:string,quantity:int,unit_amount_minor:int}>
     * } $data
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $tenantId = app(TenantContext::class)->id();
            $student = Student::query()->findOrFail($data['student_id']);
            if ($student->tenant_id !== $tenantId) {
                throw ValidationException::withMessages(['student_id' => 'Student belongs to a different tenant.']);
            }

            $term = null;
            $termLabel = '';
            $academicYearId = null;
            $academicYearLabel = '';
            if (! empty($data['term_id'])) {
                $term = Term::query()->with('academicYear')->findOrFail($data['term_id']);
                $termLabel = (string) ($term->name ?? '');
                $academicYearId = $term->academic_year_id;
                $academicYearLabel = (string) ($term->academicYear?->label ?? $term->academicYear?->name ?? '');
            }

            $primaryGuardian = $student->guardians()->wherePivot('is_primary', true)->first()
                ?? $student->guardians()->first();

            $currency = CurrencyCode::from((string) ($data['currency'] ?? config('finance.defaults.currency')));
            $issuedOn = $data['issued_on'] ?? now()->toDateString();
            $dueOn = $data['due_on'] ?? now()->addDays((int) config('finance.defaults.invoice_due_days', 20))->toDateString();

            $invoice = new Invoice;
            $invoice->fill([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next((string) $tenantId, (int) date('Y', strtotime($issuedOn))),
                'student_id' => $student->id,
                'student_name' => (string) $student->full_name,
                'student_initials' => (string) $student->avatar_initials,
                'grade_label' => (string) $student->grade_label,
                'guardian_name' => (string) ($primaryGuardian?->full_name ?? ''),
                'academic_year_id' => $academicYearId,
                'academic_year_label' => $academicYearLabel,
                'term_id' => $term?->id,
                'term_label' => $termLabel,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,
                'currency' => $currency->value,
                'subtotal_minor' => 0,
                'discount_minor' => (int) ($data['discount_minor'] ?? 0),
                'total_minor' => 0,
                'paid_minor' => 0,
                'balance_minor' => 0,
                'status' => InvoiceStatus::Draft->value,
            ]);
            $invoice->save();

            $this->replaceLines($invoice, $data['lines'] ?? []);
            $this->recomputeTotals($invoice);

            InvoiceDrafted::dispatch($invoice);

            return $invoice->refresh()->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft invoices can be edited; void and re-issue instead.',
                ]);
            }
            foreach (['issued_on', 'due_on'] as $k) {
                if (isset($data[$k])) {
                    $invoice->{$k} = $data[$k];
                }
            }
            if (isset($data['discount_minor'])) {
                $invoice->discount_minor = (int) $data['discount_minor'];
            }
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($invoice, (array) $data['lines']);
            }
            $invoice->save();
            $this->recomputeTotals($invoice);

            InvoiceUpdated::dispatch($invoice);

            return $invoice->refresh()->load('lines');
        });
    }

    public function recomputeTotals(Invoice $invoice): void
    {
        $invoice->refresh()->load('lines');
        $subtotal = (int) $invoice->lines->sum('amount_minor');
        $discount = max(0, (int) $invoice->discount_minor);
        $total = max(0, $subtotal - $discount);
        $paid = (int) $invoice->paid_minor;
        $balance = max(0, $total - $paid);

        $invoice->subtotal_minor = $subtotal;
        $invoice->total_minor = $total;
        $invoice->balance_minor = $balance;

        // status transitions once posted
        if ($invoice->status !== InvoiceStatus::Draft && $invoice->status !== InvoiceStatus::Void) {
            if ($paid >= $total && $total > 0) {
                $invoice->status = InvoiceStatus::Paid;
            } elseif ($paid > 0 && $paid < $total) {
                $invoice->status = InvoiceStatus::PartiallyPaid;
            } elseif ($invoice->due_on && $invoice->due_on->isPast() && $paid < $total) {
                $invoice->status = InvoiceStatus::Overdue;
            } else {
                $invoice->status = InvoiceStatus::Issued;
            }
        }
        $invoice->save();
    }

    /**
     * @param  list<array{fee_structure_id?:?string,description:string,category:string,quantity:int,unit_amount_minor:int}>  $lines
     */
    private function replaceLines(Invoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();
        $position = 0;
        foreach ($lines as $l) {
            $qty = max(1, (int) ($l['quantity'] ?? 1));
            $unit = max(0, (int) $l['unit_amount_minor']);
            InvoiceLine::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'fee_structure_id' => $l['fee_structure_id'] ?? null,
                'description' => (string) $l['description'],
                'category' => FeeCategory::from((string) $l['category'])->value,
                'quantity' => $qty,
                'unit_amount_minor' => $unit,
                'amount_minor' => $qty * $unit,
                'position' => $position++,
            ]);
        }
    }
}
