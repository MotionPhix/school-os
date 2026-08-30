<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\CalendarEventPublished;
use App\Models\CalendarEvent;
use Illuminate\Support\Facades\DB;

final class WriteCalendarEvent
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?CalendarEvent $existing = null): CalendarEvent
    {
        return DB::transaction(function () use ($data, $existing): CalendarEvent {
            $event = $existing ?? new CalendarEvent;
            $event->fill($data);
            $event->save();

            if ($existing === null) {
                CalendarEventPublished::dispatch($event);
            }

            return $event->fresh();
        });
    }
}
