<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Role;
use Illuminate\Http\Request;

/**
 * @mixin Role
 */
final class RoleResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'scope' => $this->scope->value,
            'is_system' => $this->is_system,
            'permission_keys' => $this->permission_keys,
            'member_count' => (int) ($this->member_count ?? 0),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
