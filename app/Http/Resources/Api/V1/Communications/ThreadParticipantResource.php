<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\ThreadParticipant;
use Illuminate\Http\Request;

/**
 * @mixin ThreadParticipant
 */
final class ThreadParticipantResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'role' => $this->role->value,
            'avatar_initials' => $this->avatar_initials,
        ];
    }
}
