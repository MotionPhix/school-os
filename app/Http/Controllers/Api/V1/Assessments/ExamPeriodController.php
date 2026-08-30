<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Domains\Assessments\Services\BulkAssessmentsAction;
use App\Domains\Assessments\Services\SetExamPeriodStatus;
use App\Domains\Assessments\Services\WriteExamPeriod;
use App\Enums\ExamPeriodStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Assessments\BulkExamPeriodsRequest;
use App\Http\Requests\Api\V1\Assessments\SetExamPeriodStatusRequest;
use App\Http\Requests\Api\V1\Assessments\StoreExamPeriodRequest;
use App\Http\Requests\Api\V1\Assessments\UpdateExamPeriodRequest;
use App\Http\Resources\Api\V1\Assessments\ExamPeriodResource;
use App\Models\ExamPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExamPeriodController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExamPeriod::class);

        $query = ExamPeriod::query()
            ->with(['term', 'academicYear'])
            ->withCount('exams');

        if ($termId = $request->string('term_id')->toString()) {
            $query->where('term_id', $termId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->orderByDesc('starts_on')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            ExamPeriodResource::collection($paginator),
            $paginator,
        );
    }

    public function show(ExamPeriod $examPeriod): JsonResponse
    {
        $this->authorize('view', $examPeriod);
        $examPeriod->load(['term', 'academicYear'])->loadCount('exams');

        return $this->respond(new ExamPeriodResource($examPeriod));
    }

    public function store(StoreExamPeriodRequest $request, WriteExamPeriod $service): JsonResponse
    {
        $period = $service->create($request->validated());
        $period->load(['term', 'academicYear'])->loadCount('exams');

        return $this->respondCreated(new ExamPeriodResource($period));
    }

    public function update(UpdateExamPeriodRequest $request, ExamPeriod $examPeriod, WriteExamPeriod $service): JsonResponse
    {
        $period = $service->update($examPeriod, $request->validated());
        $period->load(['term', 'academicYear'])->loadCount('exams');

        return $this->respond(new ExamPeriodResource($period));
    }

    public function setStatus(SetExamPeriodStatusRequest $request, ExamPeriod $examPeriod, SetExamPeriodStatus $service): JsonResponse
    {
        $period = $service->handle($examPeriod, ExamPeriodStatus::from($request->string('status')));
        $period->load(['term', 'academicYear'])->loadCount('exams');

        return $this->respond(new ExamPeriodResource($period));
    }

    public function destroy(ExamPeriod $examPeriod): JsonResponse
    {
        $this->authorize('delete', $examPeriod);
        $examPeriod->delete();

        return $this->respondNoContent();
    }

    /**
     * POST /exam-periods/bulk — lifecycle batch across periods.
     */
    public function bulk(BulkExamPeriodsRequest $request, BulkAssessmentsAction $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => $service->periods($data['ids'], $data['action'])]);
    }
}
