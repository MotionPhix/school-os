<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\TimetableSlot;
use App\Support\Events\BusinessEvent;

final class TimetableSlotRemoved extends BusinessEvent
{
    public function __construct(public readonly TimetableSlot $slot)
    {
        parent::__construct($slot->tenant_id);
    }

    public function name(): string
    {
        return 'academics.timetable_slot.removed';
    }

    public function payload(): array
    {
        return [
            'timetable_slot_id' => $this->slot->id,
            'course_section_id' => $this->slot->course_section_id,
            'weekday' => $this->slot->weekday->value,
            'period' => $this->slot->period,
        ];
    }
}
