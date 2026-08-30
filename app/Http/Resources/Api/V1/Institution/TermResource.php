<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Institution;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Term;
use Illuminate\Http\Request;

/**
 * @mixin Term
 */
final class TermResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'sequence' => (int) $this->sequence,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'instructional_days' => (int) $this->instructional_days,
            'status' => $this->status->value,
        ];
    }
}
