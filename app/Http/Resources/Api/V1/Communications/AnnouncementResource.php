<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * @mixin Announcement
 */
final class AnnouncementResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience->value,
            'audience_label' => $this->audience_label,
            'channels' => $this->channels,
            'status' => $this->status->value,
            'author_name' => $this->author_name,
            'scheduled_for' => $this->iso($this->scheduled_for),
            'sent_at' => $this->iso($this->sent_at),
            'recipient_count' => (int) $this->recipient_count,
            'delivered_count' => (int) $this->delivered_count,
            'read_count' => (int) $this->read_count,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
