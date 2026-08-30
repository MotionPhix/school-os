<?php

declare(strict_types=1);

namespace App\Domains\Institution\Events;

use App\Models\CalendarEvent;
use App\Support\Events\BusinessEvent;

final class CalendarEventPublished extends BusinessEvent
{
    public function __construct(public readonly CalendarEvent $event)
    {
        parent::__construct($event->tenant_id);
    }

    public function name(): string
    {
        return 'institution.calendar_event.published';
    }

    public function payload(): array
    {
        return [
            'calendar_event_id' => $this->event->id,
            'kind' => $this->event->kind->value,
            'starts_on' => $this->event->starts_on->toDateString(),
            'ends_on' => $this->event->ends_on->toDateString(),
            'campus_id' => $this->event->campus_id,
        ];
    }
}
