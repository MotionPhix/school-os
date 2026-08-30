<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime delivery-progress tick for a communications broadcast,
 * emitted when a broadcast starts and when it completes. The creator
 * listens on `private-users.{id}` for `broadcast.progress.updated`.
 */
final class BroadcastProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $broadcastId,
        public readonly string $creatorId,
        public readonly string $status,
        public readonly int $recipientCount,
        public readonly int $deliveredCount,
        public readonly int $failedCount,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->creatorId}")];
    }

    public function broadcastAs(): string
    {
        return 'broadcast.progress.updated';
    }

    /** @return array{broadcast_id: string, status: string, recipient_count: int, delivered_count: int, failed_count: int} */
    public function broadcastWith(): array
    {
        return [
            'broadcast_id' => $this->broadcastId,
            'status' => $this->status,
            'recipient_count' => $this->recipientCount,
            'delivered_count' => $this->deliveredCount,
            'failed_count' => $this->failedCount,
        ];
    }
}
