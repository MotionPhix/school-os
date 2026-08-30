<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\Payment;
use App\Support\Events\BusinessEvent;

final class PaymentRefunded extends BusinessEvent
{
    public function __construct(public readonly Payment $payment, public readonly string $reversalEntryId)
    {
        parent::__construct($payment->tenant_id);
    }

    public function name(): string
    {
        return 'finance.payment.refunded';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'invoice_id' => $this->payment->invoice_id,
            'amount_minor' => (int) $this->payment->amount_minor,
            'reversal_entry_id' => $this->reversalEntryId,
        ];
    }
}
