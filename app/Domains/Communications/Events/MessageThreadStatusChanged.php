<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\MessageThread;
use App\Support\Events\BusinessEvent;

final class MessageThreadStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly MessageThread $thread,
        public readonly string $previousStatus,
    ) {
        parent::__construct($thread->tenant_id);
    }

    public function name(): string
    {
        return 'communications.thread.status_changed';
    }

    public function payload(): array
    {
        return [
            'thread_id' => $this->thread->id,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->thread->status->value,
        ];
    }
}
