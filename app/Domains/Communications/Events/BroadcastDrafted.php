<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\Broadcast;
use App\Support\Events\BusinessEvent;

final class BroadcastDrafted extends BusinessEvent
{
    public function __construct(public readonly Broadcast $broadcast)
    {
        parent::__construct($broadcast->tenant_id);
    }

    public function name(): string
    {
        return 'communications.broadcast.drafted';
    }

    public function payload(): array
    {
        return [
            'broadcast_id' => $this->broadcast->id,
            'channel' => $this->broadcast->channel->value,
            'audience' => $this->broadcast->audience->value,
        ];
    }
}
