<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\Invoice;
use App\Support\Events\BusinessEvent;

final class InvoiceIssued extends BusinessEvent
{
    public function __construct(public readonly Invoice $invoice, public readonly string $journalEntryId)
    {
        parent::__construct($invoice->tenant_id);
    }

    public function name(): string
    {
        return 'finance.invoice.issued';
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'total_minor' => (int) $this->invoice->total_minor,
            'journal_entry_id' => $this->journalEntryId,
        ];
    }
}
