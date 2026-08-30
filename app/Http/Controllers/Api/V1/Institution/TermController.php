<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Institution;

use App\Domains\Institution\Services\TransitionTerm;
use App\Domains\Institution\Services\WriteTerm;
use App\Enums\TermStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Institution\StoreTermRequest;
use App\Http\Requests\Api\V1\Institution\TransitionTermRequest;
use App\Http\Requests\Api\V1\Institution\UpdateTermRequest;
use App\Http\Resources\Api\V1\Institution\TermResource;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class TermController extends CapabilityController
{
    public function index(AcademicYear $academic_year): JsonResponse
    {
        $this->authorize('viewAny', Term::class);

        return TermResource::collection(
            $academic_year->terms()->orderBy('sequence')->get(),
        )->response();
    }

    public function store(AcademicYear $academic_year, StoreTermRequest $request, WriteTerm $service): JsonResponse
    {
        $term = $service->handle($academic_year, $request->validated());

        return $this->respondCreated(new TermResource($term));
    }

    public function update(AcademicYear $academic_year, Term $term, UpdateTermRequest $request, WriteTerm $service): JsonResponse
    {
        $term = $service->handle($academic_year, $request->validated(), $term);

        return $this->respond(new TermResource($term));
    }

    /** Lifecycle: upcoming -> in_progress -> completed (with supervised reopen). */
    public function transition(
        AcademicYear $academic_year,
        Term $term,
        TransitionTermRequest $request,
        TransitionTerm $service,
    ): JsonResponse {
        $term = $service->handle($term, TermStatus::from($request->validated('status')));

        return $this->respond(new TermResource($term));
    }

    public function destroy(AcademicYear $academic_year, Term $term): JsonResponse
    {
        $this->authorize('delete', $term);

        if ($term->status === TermStatus::InProgress) {
            throw ValidationException::withMessages([
                'term' => 'A term that is in progress cannot be deleted.',
            ]);
        }

        $term->delete();

        return $this->respondNoContent();
    }
}
