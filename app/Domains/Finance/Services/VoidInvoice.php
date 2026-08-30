<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\InvoiceVoided;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverses a posted invoice. Refuses to void when payments have been
 * received — those must be refunded first so the ledger stays honest.
 * The reversal is a NEW journal entry that mirrors the original with
 * sides flipped; the original is never mutated.
 */
final class VoidInvoice
{
    public function __construct(private readonly PostJournalEntry $journal) {}

    public function handle(Invoice $invoice, ?User $actor = null, ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            if ($invoice->status === InvoiceStatus::Void) {
                return $invoice;
            }
            if ($invoice->status === InvoiceStatus::Draft) {
                // No ledger impact — just mark it void.
                $invoice->status = InvoiceStatus::Void;
                $invoice->save();
                InvoiceVoided::dispatch($invoice, null);

                return $invoice;
            }
            $hasPayments = $invoice->payments()
                ->where('status', PaymentStatus::Succeeded->value)
                ->exists();
            if ($hasPayments) {
                throw ValidationException::withMessages([
                    'status' => 'Refund all payments before voiding this invoice.',
                ]);
            }

            $issue = JournalEntry::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('source_type', 'invoice')
                ->where('source_id', $invoice->id)
                ->where('is_reversal', false)
                ->orderBy('posted_at')
                ->first();

            $reversal = $issue
                ? $this->journal->reverse($issue, "{$invoice->number} voided", $reason, $actor?->id)
                : null;

            $invoice->status = InvoiceStatus::Void;
            $invoice->save();

            InvoiceVoided::dispatch($invoice, $reversal?->id);

            return $invoice->refresh();
        });
    }
}
