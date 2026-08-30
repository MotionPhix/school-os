<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\MessageThread;
use Illuminate\Http\Request;

/**
 * @mixin MessageThread
 */
final class MessageThreadResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'participants' => ThreadParticipantResource::collection(
                $this->whenLoaded('participants', fn () => $this->participants, fn () => collect()),
            )->resolve(),
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->iso($this->last_message_at),
            'unread_count' => (int) $this->unread_count,
            'student_id' => $this->student_id,
            'student_name' => $this->student_name,
            'messages' => $this->whenLoaded(
                'messages',
                fn () => ThreadMessageResource::collection($this->messages)->resolve(),
            ),
        ];
    }
}
