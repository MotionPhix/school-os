<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academics;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Subject;
use Illuminate\Http\Request;

/**
 * @mixin Subject
 */
final class SubjectResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category->value,
            'stages' => $this->stages ?? [],
            'is_core' => (bool) $this->is_core,
            'credit_hours' => (int) $this->credit_hours,
            'description' => $this->description,
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
