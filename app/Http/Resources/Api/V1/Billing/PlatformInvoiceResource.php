<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PlatformInvoiceResource extends JsonResource
{
    /**
     * @param  Collection<int, PlatformPayment>  $payments
     */
    public function __construct(
        PlatformInvoice $invoice,
        private readonly Collection $payments,
        private readonly int $totalPaidMinor,
    ) {
        parent::__construct($invoice);
    }

    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        /** @var PlatformInvoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'period' => $invoice->period,
            'amount_minor' => $invoice->amount_minor,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'issued_at' => $invoice->issued_at->toISOString(),
            'due_at' => $invoice->due_at?->toISOString(),
            'paid_at' => $invoice->paid_at?->toISOString(),
            'total_paid_minor' => $this->totalPaidMinor,
            'payments' => $this->payments->map(fn (PlatformPayment $p) => (new PlatformPaymentResource($p))->toArray($request))->values(),
        ];
    }
}
