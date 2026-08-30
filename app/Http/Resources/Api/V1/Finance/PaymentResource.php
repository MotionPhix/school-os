<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Finance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * @mixin Payment
 */
final class PaymentResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice_number,
            'student_name' => $this->student_name,
            'reference' => $this->reference,
            'method' => $this->method->value,
            'gateway' => $this->gateway,
            'amount_minor' => (int) $this->amount_minor,
            'gateway_fee_minor' => (int) $this->gateway_fee_minor,
            'currency' => $this->currency->value,
            'status' => $this->status->value,
            'received_at' => $this->iso($this->received_at),
            'note' => $this->note,
        ];
    }
}
