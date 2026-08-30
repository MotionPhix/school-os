<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academics;

use App\Domains\Academics\Services\ScheduleTimetableSlot;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Academics\MoveTimetableSlotRequest;
use App\Http\Requests\Api\V1\Academics\ScheduleTimetableSlotRequest;
use App\Http\Resources\Api\V1\Academics\TimetableSlotResource;
use App\Models\CourseSection;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TimetableController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $query = TimetableSlot::query()->with(['courseSection.subject', 'courseSection.teacher']);

        if ($weekday = $request->string('weekday')->toString()) {
            $query->where('weekday', $weekday);
        }
        if ($sectionId = $request->string('course_section_id')->toString()) {
            $query->where('course_section_id', $sectionId);
        }
        if ($teacherId = $request->string('teacher_id')->toString()) {
            $query->whereHas('courseSection', fn ($q) => $q->where('teacher_id', $teacherId));
        }
        if ($yearId = $request->string('academic_year_id')->toString()) {
            $query->whereHas('courseSection', fn ($q) => $q->where('academic_year_id', $yearId));
        }
        if ($campusId = $request->string('campus_id')->toString()) {
            $query->whereHas('courseSection', fn ($q) => $q->where('campus_id', $campusId));
        }

        $slots = $query->orderBy('weekday')->orderBy('period')->get();

        return response()->json([
            'data' => TimetableSlotResource::collection($slots)->resolve(),
        ]);
    }

    public function schedule(ScheduleTimetableSlotRequest $request, CourseSection $courseSection, ScheduleTimetableSlot $service): JsonResponse
    {
        $slot = $service->schedule($courseSection, $request->validated());
        $slot->load(['courseSection.subject', 'courseSection.teacher']);

        return $this->respondCreated(new TimetableSlotResource($slot));
    }

    public function destroy(TimetableSlot $timetableSlot, ScheduleTimetableSlot $service): JsonResponse
    {
        $this->authorize('delete', $timetableSlot);
        $service->remove($timetableSlot);

        return $this->respondNoContent();
    }

    /**
     * Move a slot to another (weekday, period) cell; clash guards still apply.
     */
    public function move(
        MoveTimetableSlotRequest $request,
        TimetableSlot $timetableSlot,
        ScheduleTimetableSlot $service,
    ): JsonResponse {
        $slot = $service->move(
            $timetableSlot,
            $request->string('weekday')->toString(),
            (int) $request->integer('period'),
        );
        $slot->load(['courseSection.subject', 'courseSection.teacher']);

        return $this->respond(new TimetableSlotResource($slot));
    }
}
