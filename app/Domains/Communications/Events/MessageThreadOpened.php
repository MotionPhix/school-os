<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\MessageThread;
use App\Support\Events\BusinessEvent;

final class MessageThreadOpened extends BusinessEvent
{
    public function __construct(public readonly MessageThread $thread)
    {
        parent::__construct($thread->tenant_id);
    }

    public function name(): string
    {
        return 'communications.thread.opened';
    }

    public function payload(): array
    {
        return [
            'thread_id' => $this->thread->id,
            'subject' => $this->thread->subject,
            'student_id' => $this->thread->student_id,
        ];
    }
}
