<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime push of a new thread reply to everyone viewing the thread's
 * presence channel. Event name: `thread.reply.created`.
 */
final class ThreadReplyCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $threadId,
        public readonly string $messageId,
        public readonly string $authorName,
        public readonly string $preview,
        public readonly string $sentAt,
    ) {}

    /** @return list<PresenceChannel> */
    public function broadcastOn(): array
    {
        return [new PresenceChannel("threads.{$this->threadId}")];
    }

    public function broadcastAs(): string
    {
        return 'thread.reply.created';
    }

    /** @return array{thread_id: string, message_id: string, author_name: string, preview: string, sent_at: string} */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'message_id' => $this->messageId,
            'author_name' => $this->authorName,
            'preview' => $this->preview,
            'sent_at' => $this->sentAt,
        ];
    }
}
