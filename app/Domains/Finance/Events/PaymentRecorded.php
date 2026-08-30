<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\Payment;
use App\Support\Events\BusinessEvent;

final class PaymentRecorded extends BusinessEvent
{
    public function __construct(public readonly Payment $payment, public readonly string $journalEntryId)
    {
        parent::__construct($payment->tenant_id);
    }

    public function name(): string
    {
        return 'finance.payment.recorded';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'invoice_id' => $this->payment->invoice_id,
            'amount_minor' => (int) $this->payment->amount_minor,
            'gateway' => $this->payment->gateway,
            'reference' => $this->payment->reference,
            'journal_entry_id' => $this->journalEntryId,
        ];
    }
}
