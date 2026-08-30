<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime timetable change push: when a slot is scheduled, moved or
 * removed, the affected section's teacher (private channel) is notified
 * so their schedule view stays current. Clashes are still hard-rejected
 * server-side; this keeps stakeholders informed of accepted changes.
 */
final class TimetableChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $slotId,
        public readonly string $sectionId,
        public readonly string $sectionLabel,
        public readonly string $weekday,
        public readonly int $period,
        public readonly ?string $room,
        public readonly string $action,
        public readonly string $teacherUserId,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->teacherUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'timetable.changed';
    }

    /** @return array{slot_id: string, section_id: string, section_label: string, weekday: string, period: int, room: ?string, action: string} */
    public function broadcastWith(): array
    {
        return [
            'slot_id' => $this->slotId,
            'section_id' => $this->sectionId,
            'section_label' => $this->sectionLabel,
            'weekday' => $this->weekday,
            'period' => $this->period,
            'room' => $this->room,
            'action' => $this->action,
        ];
    }
}
