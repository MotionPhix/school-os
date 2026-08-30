<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\FeeStructure;
use App\Support\Events\BusinessEvent;

final class FeeStructureToggled extends BusinessEvent
{
    public function __construct(public readonly FeeStructure $fee)
    {
        parent::__construct($fee->tenant_id);
    }

    public function name(): string
    {
        return 'finance.fee_structure.toggled';
    }

    public function payload(): array
    {
        return [
            'fee_structure_id' => $this->fee->id,
            'is_active' => (bool) $this->fee->is_active,
        ];
    }
}
