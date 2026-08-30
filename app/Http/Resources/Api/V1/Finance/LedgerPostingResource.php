<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Finance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\LedgerPosting;
use Illuminate\Http\Request;

/**
 * @mixin LedgerPosting
 */
final class LedgerPostingResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_entry_id' => $this->journal_entry_id,
            'account_id' => $this->account_id,
            'side' => $this->side,
            'amount_minor' => (int) $this->amount_minor,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'currency' => $this->currency->value,
            'memo' => $this->memo,
            'reference' => $this->entry?->reference,
        ];
    }
}
