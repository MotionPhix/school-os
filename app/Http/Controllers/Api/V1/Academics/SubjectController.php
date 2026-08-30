<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academics;

use App\Domains\Academics\Services\BulkAcademicsAction;
use App\Domains\Academics\Services\WriteSubject;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Academics\BulkSubjectsRequest;
use App\Http\Requests\Api\V1\Academics\StoreSubjectRequest;
use App\Http\Requests\Api\V1\Academics\UpdateSubjectRequest;
use App\Http\Resources\Api\V1\Academics\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubjectController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subject::class);

        $query = Subject::query();

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }
        if ($stage = $request->string('stage')->toString()) {
            $query->forStage($stage);
        }
        if ($request->boolean('core_only')) {
            $query->core();
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('code')->paginate((int) $request->integer('per_page', 50));

        return $this->respondPaginated(
            SubjectResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Subject $subject): JsonResponse
    {
        $this->authorize('view', $subject);

        return $this->respond(new SubjectResource($subject));
    }

    public function store(StoreSubjectRequest $request, WriteSubject $service): JsonResponse
    {
        $subject = $service->handle($request->validated());

        return $this->respondCreated(new SubjectResource($subject));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject, WriteSubject $service): JsonResponse
    {
        $subject = $service->handle($request->validated(), $subject);

        return $this->respond(new SubjectResource($subject));
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $this->authorize('delete', $subject);
        $subject->delete();

        return $this->respondNoContent();
    }

    /**
     * Batch category / core-flag changes and guarded deletion.
     */
    public function bulk(BulkSubjectsRequest $request, BulkAcademicsAction $service): JsonResponse
    {
        $data = $request->validated();
        $ids = $data['ids'];

        $result = match ($data['action']) {
            'set_category' => $service->updateSubjects($ids, ['category' => $data['category']]),
            'set_core' => $service->updateSubjects($ids, ['is_core' => (bool) $data['is_core']]),
            'delete' => $service->deleteSubjects($ids),
        };

        return response()->json(['data' => $result]);
    }
}
