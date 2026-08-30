<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Finance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;

/**
 * @mixin InvoiceLine
 */
final class InvoiceLineResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fee_structure_id' => $this->fee_structure_id,
            'description' => $this->description,
            'category' => $this->category->value,
            'quantity' => (int) $this->quantity,
            'unit_amount_minor' => (int) $this->unit_amount_minor,
            'amount_minor' => (int) $this->amount_minor,
        ];
    }
}
