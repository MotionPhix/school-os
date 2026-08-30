<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\InvoiceReminderSent;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chase an outstanding balance. Only posted invoices with money still
 * owed can be chased — drafts have never been seen by the guardian and
 * voided/settled invoices have nothing to collect. The Communications
 * capability listens for InvoiceReminderSent and does the delivery.
 */
final class SendInvoiceReminder
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only issued invoices can be chased.',
                ]);
            }
            if ((int) $invoice->balance_minor <= 0) {
                throw ValidationException::withMessages([
                    'status' => 'Nothing outstanding to chase.',
                ]);
            }

            $invoice->last_reminded_at = now();
            $invoice->save();

            InvoiceReminderSent::dispatch($invoice);

            return $invoice->refresh();
        });
    }
}
