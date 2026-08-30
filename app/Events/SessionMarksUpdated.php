<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime attendance register update: pushed to everyone viewing the
 * session's presence channel whenever a mark changes, so the status count
 * bars stay live. Event name: `session.marks.updated`.
 */
final class SessionMarksUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $sessionId,
        public readonly int $presentCount,
        public readonly int $absentCount,
        public readonly int $lateCount,
        public readonly int $excusedCount,
        public readonly int $totalCount,
    ) {}

    /** @return list<PresenceChannel> */
    public function broadcastOn(): array
    {
        return [new PresenceChannel("sessions.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'session.marks.updated';
    }

    /** @return array{session_id: string, present_count: int, absent_count: int, late_count: int, excused_count: int, total_count: int} */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'present_count' => $this->presentCount,
            'absent_count' => $this->absentCount,
            'late_count' => $this->lateCount,
            'excused_count' => $this->excusedCount,
            'total_count' => $this->totalCount,
        ];
    }
}
