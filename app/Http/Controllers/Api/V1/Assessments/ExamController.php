<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Domains\Assessments\Services\BulkAssessmentsAction;
use App\Domains\Assessments\Services\BulkSetExamResults;
use App\Domains\Assessments\Services\SetExamResult;
use App\Domains\Assessments\Services\SetExamStatus;
use App\Domains\Assessments\Services\WriteExam;
use App\Enums\ExamStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Assessments\BulkExamsRequest;
use App\Http\Requests\Api\V1\Assessments\BulkSetExamResultsRequest;
use App\Http\Requests\Api\V1\Assessments\CurveExamResultsRequest;
use App\Http\Requests\Api\V1\Assessments\FillExamResultsRequest;
use App\Http\Requests\Api\V1\Assessments\SetExamResultRequest;
use App\Http\Requests\Api\V1\Assessments\SetExamStatusRequest;
use App\Http\Requests\Api\V1\Assessments\StoreExamRequest;
use App\Http\Requests\Api\V1\Assessments\UpdateExamRequest;
use App\Http\Resources\Api\V1\Assessments\ExamResource;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExamController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Exam::class);

        $query = Exam::query()
            ->with(['period', 'courseSection.subject', 'courseSection.teacher'])
            ->withCount(['results as graded_count' => fn ($q) => $q->whereNotNull('score')]);

        if ($periodId = $request->string('period_id')->toString()) {
            $query->where('period_id', $periodId);
        }
        if ($sectionId = $request->string('course_section_id')->toString()) {
            $query->where('course_section_id', $sectionId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->orderByDesc('scheduled_on')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            ExamResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Exam $exam): JsonResponse
    {
        $this->authorize('view', $exam);
        $exam->load([
            'period',
            'courseSection.subject',
            'courseSection.teacher',
            'results.student',
        ])->loadCount(['results as graded_count' => fn ($q) => $q->whereNotNull('score')]);

        return $this->respond(new ExamResource($exam));
    }

    public function store(StoreExamRequest $request, WriteExam $service): JsonResponse
    {
        $exam = $service->create($request->validated());
        $exam->load(['period', 'courseSection.subject', 'courseSection.teacher']);

        return $this->respondCreated(new ExamResource($exam));
    }

    public function update(UpdateExamRequest $request, Exam $exam, WriteExam $service): JsonResponse
    {
        $exam = $service->update($exam, $request->validated());
        $exam->load(['period', 'courseSection.subject', 'courseSection.teacher']);

        return $this->respond(new ExamResource($exam));
    }

    public function setStatus(SetExamStatusRequest $request, Exam $exam, SetExamStatus $service): JsonResponse
    {
        $exam = $service->handle($exam, ExamStatus::from($request->string('status')), $request->user());
        $exam->load(['period', 'courseSection.subject', 'courseSection.teacher']);

        return $this->respond(new ExamResource($exam));
    }

    public function destroy(Exam $exam): JsonResponse
    {
        $this->authorize('delete', $exam);
        $exam->delete();

        return $this->respondNoContent();
    }

    /**
     * POST /exams/bulk — lifecycle batch across papers.
     */
    public function bulk(BulkExamsRequest $request, BulkAssessmentsAction $service): JsonResponse
    {
        $data = $request->validated();
        $out = $service->exams($data['ids'], $data['action'], $request->user());

        return response()->json(['data' => $out]);
    }

    /**
     * POST /exams/{exam}/results — set/clear one student's score.
     */
    public function setResult(SetExamResultRequest $request, Exam $exam, SetExamResult $service): JsonResponse
    {
        $data = $request->validated();
        $student = Student::query()->findOrFail($data['student_id']);
        $service->handle($exam, $student, [
            'score' => array_key_exists('score', $data) ? $data['score'] : null,
            'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : null,
        ], $request->user());

        $exam->refresh()->load([
            'period',
            'courseSection.subject',
            'courseSection.teacher',
            'results.student',
        ])->loadCount(['results as graded_count' => fn ($q) => $q->whereNotNull('score')]);

        return $this->respond(new ExamResource($exam));
    }

    /**
     * POST /exams/{exam}/results/bulk — save many edited marksheet cells.
     */
    public function bulkResults(BulkSetExamResultsRequest $request, Exam $exam, BulkSetExamResults $service): JsonResponse
    {
        $saved = $service->save($exam, $request->validated()['entries'], $request->user());

        return $this->respond($this->reloadExam($exam)->additional(['meta' => ['saved' => $saved]]));
    }

    /**
     * POST /exams/{exam}/results/fill — quick marks across the roster.
     */
    public function fillResults(FillExamResultsRequest $request, Exam $exam, BulkSetExamResults $service): JsonResponse
    {
        $data = $request->validated();
        $changed = $service->fill($exam, $data['scope'], (int) $data['score'], $request->user());

        return $this->respond($this->reloadExam($exam)->additional(['meta' => ['changed' => $changed]]));
    }

    /**
     * POST /exams/{exam}/results/curve — moderate every graded score.
     */
    public function curveResults(CurveExamResultsRequest $request, Exam $exam, BulkSetExamResults $service): JsonResponse
    {
        $data = $request->validated();
        $changed = $service->curve($exam, $data['mode'], (float) $data['amount'], $request->user());

        return $this->respond($this->reloadExam($exam)->additional(['meta' => ['changed' => $changed]]));
    }

    private function reloadExam(Exam $exam): ExamResource
    {
        $exam->refresh()->load([
            'period',
            'courseSection.subject',
            'courseSection.teacher',
            'results.student',
        ])->loadCount(['results as graded_count' => fn ($q) => $q->whereNotNull('score')]);

        return new ExamResource($exam);
    }
}
