<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Institution;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Campus;
use Illuminate\Http\Request;

/**
 * @mixin Campus
 */
final class CampusResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'is_primary' => (bool) $this->is_primary,
            'status' => $this->status->value,
            'address_line' => $this->address_line,
            'city' => $this->city,
            'region' => $this->region,
            'timezone' => $this->timezone,
            'student_count' => (int) ($this->students_count ?? $this->students()->enrolled()->count()),
            'staff_count' => (int) ($this->staff_members_count ?? $this->staffMembers()->active()->count()),
            'building_count' => (int) $this->building_count,
            'opened_at' => $this->iso($this->opened_at),
        ];
    }
}
