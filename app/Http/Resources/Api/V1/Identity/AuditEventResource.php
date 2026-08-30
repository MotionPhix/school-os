<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\AuditEvent;
use Illuminate\Http\Request;

/**
 * @mixin AuditEvent
 */
final class AuditEventResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'actor_id' => $this->actor_id,
            'actor_name' => $this->actor_name,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_label' => $this->subject_label,
            'summary' => $this->summary,
            'metadata' => (object) ($this->metadata ?? []),
            'occurred_at' => $this->iso($this->occurred_at),
        ];
    }
}
