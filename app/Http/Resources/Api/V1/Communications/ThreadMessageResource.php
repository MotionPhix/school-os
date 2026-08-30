<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\ThreadMessage;
use Illuminate\Http\Request;

/**
 * @mixin ThreadMessage
 */
final class ThreadMessageResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'author_id' => $this->author_id,
            'author_name' => $this->author_name,
            'author_role' => $this->author_role->value,
            'body' => $this->body,
            'sent_at' => $this->iso($this->sent_at),
            'read' => (bool) $this->read,
        ];
    }
}
