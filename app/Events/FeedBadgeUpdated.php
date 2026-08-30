<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime push of the recipient's unread in-app notification count,
 * emitted whenever a notification is stored for them. Subscribers listen
 * on `private-users.{id}` for `feed.badge.updated`.
 */
final class FeedBadgeUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly int $unreadCount,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'feed.badge.updated';
    }

    /** @return array{user_id: string, unread_count: int} */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'unread_count' => $this->unreadCount,
        ];
    }
}
