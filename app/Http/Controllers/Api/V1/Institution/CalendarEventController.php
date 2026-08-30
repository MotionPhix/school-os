<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Institution;

use App\Domains\Institution\Services\WriteCalendarEvent;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Institution\StoreCalendarEventRequest;
use App\Http\Requests\Api\V1\Institution\UpdateCalendarEventRequest;
use App\Http\Resources\Api\V1\Institution\CalendarEventResource;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalendarEventController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $query = CalendarEvent::query()->orderBy('starts_on');

        if ($yearId = $request->query('academic_year_id')) {
            $query->where('academic_year_id', $yearId);
        }
        if ($campusId = $request->query('campus_id')) {
            $query->forCampus($campusId);
        }
        if ($from = $request->query('from')) {
            $query->where('ends_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('starts_on', '<=', $to);
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 50));

        return $this->respondPaginated(
            CalendarEventResource::collection($paginator),
            $paginator,
        );
    }

    public function show(CalendarEvent $calendar_event): JsonResponse
    {
        $this->authorize('view', $calendar_event);

        return $this->respond(new CalendarEventResource($calendar_event));
    }

    public function store(StoreCalendarEventRequest $request, WriteCalendarEvent $service): JsonResponse
    {
        $event = $service->handle($request->validated());

        return $this->respondCreated(new CalendarEventResource($event));
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendar_event, WriteCalendarEvent $service): JsonResponse
    {
        $event = $service->handle($request->validated(), $calendar_event);

        return $this->respond(new CalendarEventResource($event));
    }

    public function destroy(CalendarEvent $calendar_event): JsonResponse
    {
        $this->authorize('delete', $calendar_event);
        $calendar_event->delete();

        return $this->respondNoContent();
    }
}
