<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Invitation;
use Illuminate\Http\Request;

/**
 * @mixin Invitation
 */
final class InvitationResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'email' => $this->email,
            'role_ids' => $this->role_ids,
            'status' => $this->status->value,
            'invited_by_id' => $this->invited_by_id,
            'expires_at' => $this->iso($this->expires_at),
            'accepted_at' => $this->iso($this->accepted_at),
            'revoked_at' => $this->iso($this->revoked_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
