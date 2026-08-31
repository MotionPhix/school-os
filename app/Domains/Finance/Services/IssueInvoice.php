<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Support\FeeCategoryRevenueMap;
use App\Enums\AccountKind;
use App\Enums\FeeCategory;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LedgerPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move an invoice from draft → issued and post the accounting entry:
 *
 *     Dr  Accounts Receivable        total
 *         Cr  Revenue — Tuition       sum(tuition line amounts)
 *         Cr  Revenue — Boarding      ...
 *         Cr  ...one credit per FeeCategory present on the invoice
 *         (a discount, if any, is booked to Discounts Given as
 *          Dr Discounts Given / Cr AR — netting the receivable down)
 */
final class IssueInvoice
{
    public function __construct(
        private readonly EnsureChartOfAccounts $chart,
        private readonly PostJournalEntry $journal,
    ) {}

    public function handle(Invoice $invoice, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            // Lock the row so two concurrent issues cannot both pass the
            // draft check (double-posting race → duplicate AR entries).
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only draft invoices can be issued.']);
            }
            $invoice->loadMissing('lines');
            if ($invoice->total_minor <= 0) {
                throw ValidationException::withMessages(['lines' => 'Invoice must have at least one non-zero line.']);
            }

            $currency = $invoice->currency;
            $this->chart->forCurrency($currency, $invoice->tenant_id);

            $ar = $this->chart->get(AccountKind::AccountsReceivable, $currency, $invoice->tenant_id);

            $postings = [
                ['account_id' => $ar->id, 'side' => LedgerPosting::SIDE_DEBIT, 'amount_minor' => (int) $invoice->total_minor, 'memo' => "AR — {$invoice->number}"],
            ];

            // Group line amounts by category → one credit per revenue account.
            /** @var Collection<int, InvoiceLine> $lines */
            $lines = $invoice->lines;

            /** @var array<string,int> $byCat */
            $byCat = [];
            foreach ($lines as $line) {
                $cat = $line->category;
                $byCat[$cat->value] = ($byCat[$cat->value] ?? 0) + (int) $line->amount_minor;
            }

            // If there's a discount, book it to a discounts-given account while
            // revenue credits stay gross: debits (net total + discount) ==
            // credits (subtotal), so the entry stays balanced.
            $discount = (int) $invoice->discount_minor;
            $subtotal = array_sum($byCat);

            if ($discount > 0 && $subtotal > 0) {
                $discountsAcct = $this->chart->get(AccountKind::DiscountsGiven, $currency, $invoice->tenant_id);
                $postings = [
                    ['account_id' => $ar->id, 'side' => LedgerPosting::SIDE_DEBIT, 'amount_minor' => (int) $invoice->total_minor, 'memo' => "AR — {$invoice->number}"],
                    ['account_id' => $discountsAcct->id, 'side' => LedgerPosting::SIDE_DEBIT, 'amount_minor' => $discount, 'memo' => "Discount — {$invoice->number}"],
                ];
            }

            foreach ($byCat as $catValue => $amount) {

                if ($amount <= 0) {
                    continue;
                }
                $kind = FeeCategoryRevenueMap::for(FeeCategory::from($catValue));
                $rev = $this->chart->get($kind, $currency, $invoice->tenant_id);
                $postings[] = [
                    'account_id' => $rev->id,
                    'side' => LedgerPosting::SIDE_CREDIT,
                    'amount_minor' => $amount,
                    'memo' => "{$kind->displayName()} — {$invoice->number}",
                ];
            }

            $entry = $this->journal->handle([
                'occurred_on' => $invoice->issued_on->toDateString(),
                'reference' => "{$invoice->number} issued",
                'memo' => "Invoice {$invoice->number} for {$invoice->student_name}",
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'currency' => $currency,
                'posted_by' => $actor?->id,
                'postings' => $postings,
            ]);

            $invoice->status = InvoiceStatus::Issued;
            $invoice->save();

            InvoiceIssued::dispatch($invoice, $entry->id);

            return $invoice->refresh();
        });
    }
}
