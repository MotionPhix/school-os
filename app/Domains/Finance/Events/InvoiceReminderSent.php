<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\Invoice;
use App\Support\Events\BusinessEvent;

final class InvoiceReminderSent extends BusinessEvent
{
    public function __construct(public readonly Invoice $invoice)
    {
        parent::__construct($invoice->tenant_id);
    }

    public function name(): string
    {
        return 'finance.invoice.reminder_sent';
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'balance_minor' => (int) $this->invoice->balance_minor,
            'due_on' => $this->invoice->due_on?->toDateString(),
        ];
    }
}
