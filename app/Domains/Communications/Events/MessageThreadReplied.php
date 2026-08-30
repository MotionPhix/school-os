<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\ThreadMessage;
use App\Support\Events\BusinessEvent;

final class MessageThreadReplied extends BusinessEvent
{
    public function __construct(public readonly ThreadMessage $message)
    {
        parent::__construct($message->tenant_id);
    }

    public function name(): string
    {
        return 'communications.thread.replied';
    }

    public function payload(): array
    {
        return [
            'thread_id' => $this->message->thread_id,
            'message_id' => $this->message->id,
            'author_id' => $this->message->author_id,
            'author_role' => $this->message->author_role->value,
        ];
    }
}
