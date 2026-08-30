<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Institution;

use App\Domains\Institution\Services\SetCurrentAcademicYear;
use App\Domains\Institution\Services\TransitionAcademicYear;
use App\Domains\Institution\Services\WriteAcademicYear;
use App\Enums\AcademicYearStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Institution\StoreAcademicYearRequest;
use App\Http\Requests\Api\V1\Institution\TransitionAcademicYearRequest;
use App\Http\Requests\Api\V1\Institution\UpdateAcademicYearRequest;
use App\Http\Resources\Api\V1\Institution\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AcademicYearController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AcademicYear::class);

        $paginator = AcademicYear::query()
            ->with('terms')
            ->when(
                $request->filled('status') && $request->string('status')->value() !== 'all',
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
            ->orderByDesc('is_current')
            ->orderByDesc('starts_on')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            AcademicYearResource::collection($paginator),
            $paginator,
        );
    }

    public function show(AcademicYear $academic_year): JsonResponse
    {
        $this->authorize('view', $academic_year);
        $academic_year->load('terms');

        return $this->respond(new AcademicYearResource($academic_year));
    }

    public function store(StoreAcademicYearRequest $request, WriteAcademicYear $service): JsonResponse
    {
        $year = $service->handle($request->validated());
        $year->load('terms');

        return $this->respondCreated(new AcademicYearResource($year));
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academic_year, WriteAcademicYear $service): JsonResponse
    {
        $year = $service->handle($request->validated(), $academic_year);
        $year->load('terms');

        return $this->respond(new AcademicYearResource($year));
    }

    /** Lifecycle: planning -> active -> closed (with supervised reopen). */
    public function transition(
        TransitionAcademicYearRequest $request,
        AcademicYear $academic_year,
        TransitionAcademicYear $service,
    ): JsonResponse {
        $year = $service->handle(
            $academic_year,
            AcademicYearStatus::from($request->validated('status')),
        );
        $year->load('terms');

        return $this->respond(new AcademicYearResource($year));
    }

    /** Only a planning year (never current) can be discarded. */
    public function destroy(AcademicYear $academic_year): JsonResponse
    {
        $this->authorize('delete', $academic_year);

        $academic_year->terms()->delete();
        $academic_year->delete();

        return $this->respondNoContent();
    }

    public function setCurrent(AcademicYear $academic_year, SetCurrentAcademicYear $service): JsonResponse
    {
        $this->authorize('setCurrent', $academic_year);

        if ($academic_year->status === AcademicYearStatus::Closed) {
            throw ValidationException::withMessages([
                'status' => 'A closed academic year cannot be set as the current year.',
            ]);
        }

        $year = $service->handle($academic_year);
        $year->load('terms');

        return $this->respond(new AcademicYearResource($year));
    }
}
