<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Finance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\FeeStructure;
use Illuminate\Http\Request;

/**
 * @mixin FeeStructure
 */
final class FeeStructureResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'academic_year_label' => $this->academic_year_label,
            'grade_label' => $this->grade_label,
            'name' => $this->name,
            'category' => $this->category->value,
            'cycle' => $this->cycle->value,
            'amount_minor' => (int) $this->amount_minor,
            'currency' => $this->currency->value,
            'is_active' => (bool) $this->is_active,
            'applies_to_student_count' => (int) $this->applies_to_student_count,
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
