<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Broadcast;
use Illuminate\Http\Request;

/**
 * @mixin Broadcast
 */
final class BroadcastResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'channel' => $this->channel->value,
            'audience' => $this->audience->value,
            'audience_label' => $this->audience_label,
            'template_snippet' => $this->template_snippet,
            'status' => $this->status->value,
            'scheduled_for' => $this->iso($this->scheduled_for),
            'started_at' => $this->iso($this->started_at),
            'completed_at' => $this->iso($this->completed_at),
            'recipient_count' => (int) $this->recipient_count,
            'delivered_count' => (int) $this->delivered_count,
            'failed_count' => (int) $this->failed_count,
            'cost_minor' => (int) $this->cost_minor,
            'currency' => $this->currency->value,
        ];
    }
}
