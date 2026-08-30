<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\Invoice;
use App\Support\Events\BusinessEvent;

final class InvoiceVoided extends BusinessEvent
{
    public function __construct(public readonly Invoice $invoice, public readonly ?string $reversalEntryId)
    {
        parent::__construct($invoice->tenant_id);
    }

    public function name(): string
    {
        return 'finance.invoice.voided';
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'reversal_entry_id' => $this->reversalEntryId,
        ];
    }
}
