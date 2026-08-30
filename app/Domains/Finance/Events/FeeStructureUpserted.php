<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\FeeStructure;
use App\Support\Events\BusinessEvent;

final class FeeStructureUpserted extends BusinessEvent
{
    public function __construct(public readonly FeeStructure $fee, public readonly bool $created)
    {
        parent::__construct($fee->tenant_id);
    }

    public function name(): string
    {
        return $this->created ? 'finance.fee_structure.created' : 'finance.fee_structure.updated';
    }

    public function payload(): array
    {
        return [
            'fee_structure_id' => $this->fee->id,
            'category' => $this->fee->category->value,
            'amount_minor' => (int) $this->fee->amount_minor,
            'currency' => $this->fee->currency->value,
        ];
    }
}
