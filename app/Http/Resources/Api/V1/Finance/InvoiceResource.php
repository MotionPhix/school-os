<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Finance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * @mixin Invoice
 */
final class InvoiceResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'number' => $this->number,
            'student_id' => $this->student_id,
            'student_name' => $this->student_name,
            'student_initials' => $this->student_initials,
            'grade_label' => $this->grade_label,
            'guardian_name' => $this->guardian_name,
            'academic_year_label' => $this->academic_year_label,
            'term_label' => $this->term_label,
            'issued_on' => $this->issued_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'currency' => $this->currency->value,
            'subtotal_minor' => (int) $this->subtotal_minor,
            'discount_minor' => (int) $this->discount_minor,
            'total_minor' => (int) $this->total_minor,
            'paid_minor' => (int) $this->paid_minor,
            'balance_minor' => (int) $this->balance_minor,
            'status' => $this->status->value,
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines'))->resolve(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments'))->resolve(),
            'last_reminded_at' => $this->iso($this->last_reminded_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
