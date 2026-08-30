<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\Invoice;
use App\Support\Events\BusinessEvent;

final class InvoiceDrafted extends BusinessEvent
{
    public function __construct(public readonly Invoice $invoice)
    {
        parent::__construct($invoice->tenant_id);
    }

    public function name(): string
    {
        return 'finance.invoice.drafted';
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'student_id' => $this->invoice->student_id,
            'total_minor' => (int) $this->invoice->total_minor,
        ];
    }
}
