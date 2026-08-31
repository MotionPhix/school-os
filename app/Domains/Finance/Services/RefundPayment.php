<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\PaymentRefunded;
use App\Enums\PaymentStatus;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverse a successful payment. Posts a mirror-image journal entry
 * (Dr AR / Cr Cash / Cr Gateway Fees) so the invoice regains balance
 * and cash accounts unwind. The original payment row stays for audit;
 * we mark it Refunded and lower the invoice's paid_minor.
 */
final class RefundPayment
{
    public function __construct(
        private readonly PostJournalEntry $journal,
        private readonly WriteInvoice $writeInvoice,
    ) {}

    public function handle(Payment $payment, ?User $actor = null, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            // Lock the payment row so two concurrent refunds cannot both
            // pass the status check (double-refund race → duplicate
            // reversals and a double-decremented paid_minor).
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Succeeded) {
                throw ValidationException::withMessages(['status' => 'Only successful payments can be refunded.']);
            }
            $originalEntry = JournalEntry::query()
                ->where('tenant_id', $payment->tenant_id)
                ->where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('is_reversal', false)
                ->orderBy('posted_at')
                ->firstOrFail();

            $reversal = $this->journal->reverse(
                $originalEntry,
                "{$payment->reference} refunded",
                $reason,
                $actor?->id,
            );

            $payment->status = PaymentStatus::Refunded;
            $payment->save();

            $invoice = $payment->invoice()->lockForUpdate()->firstOrFail();
            $invoice->paid_minor = max(0, (int) $invoice->paid_minor - (int) $payment->amount_minor);
            $invoice->save();
            $this->writeInvoice->recomputeTotals($invoice);

            PaymentRefunded::dispatch($payment, $reversal->id);

            return $payment->refresh();
        });
    }
}
