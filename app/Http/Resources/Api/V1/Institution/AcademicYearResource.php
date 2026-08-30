<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Institution;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

/**
 * @mixin AcademicYear
 */
final class AcademicYearResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'label' => $this->label,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'status' => $this->status->value,
            'is_current' => (bool) $this->is_current,
            'terms' => TermResource::collection($this->whenLoaded('terms')),
        ];
    }
}
