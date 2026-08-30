<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\TimetableSlot;
use App\Support\Events\BusinessEvent;

final class TimetableSlotScheduled extends BusinessEvent
{
    public function __construct(public readonly TimetableSlot $slot)
    {
        parent::__construct($slot->tenant_id);
    }

    public function name(): string
    {
        return 'academics.timetable_slot.scheduled';
    }

    public function payload(): array
    {
        return [
            'timetable_slot_id' => $this->slot->id,
            'course_section_id' => $this->slot->course_section_id,
            'weekday' => $this->slot->weekday->value,
            'period' => $this->slot->period,
            'starts_at' => $this->slot->starts_at,
            'ends_at' => $this->slot->ends_at,
        ];
    }
}
