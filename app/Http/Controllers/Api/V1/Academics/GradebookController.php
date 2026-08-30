<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academics;

use App\Domains\Academics\Services\BulkGradebookAction;
use App\Domains\Academics\Services\UpsertGradebookEntry;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Academics\BulkGradebookRequest;
use App\Http\Requests\Api\V1\Academics\CurveGradebookRequest;
use App\Http\Requests\Api\V1\Academics\UpsertGradebookEntryRequest;
use App\Http\Resources\Api\V1\Academics\GradebookEntryResource;
use App\Models\CourseSection;
use App\Models\GradebookEntry;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GradebookController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GradebookEntry::class);

        $query = GradebookEntry::query()->with(['term', 'student']);

        if ($sectionId = $request->string('course_section_id')->toString()) {
            $query->where('course_section_id', $sectionId);
        }
        if ($termId = $request->string('term_id')->toString()) {
            $query->where('term_id', $termId);
        }
        if ($studentId = $request->string('student_id')->toString()) {
            $query->where('student_id', $studentId);
        }

        $paginator = $query
            ->orderByDesc('total')
            ->paginate((int) $request->integer('per_page', 100));

        return $this->respondPaginated(
            GradebookEntryResource::collection($paginator),
            $paginator,
        );
    }

    public function upsert(UpsertGradebookEntryRequest $request, UpsertGradebookEntry $service): JsonResponse
    {
        $data = $request->validated();
        $section = CourseSection::query()->findOrFail($data['course_section_id']);
        $term = Term::query()->findOrFail($data['term_id']);
        $student = Student::query()->findOrFail($data['student_id']);

        $entry = $service->handle(
            $section,
            $term,
            $student,
            [
                'continuous_assessment' => (int) $data['continuous_assessment'],
                'exam_score' => (int) $data['exam_score'],
                'remarks' => $data['remarks'] ?? null,
            ],
            $request->user(),
        );

        $entry->load(['term', 'student']);

        return $this->respond(new GradebookEntryResource($entry), $entry->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(GradebookEntry $gradebookEntry): JsonResponse
    {
        $this->authorize('delete', $gradebookEntry);
        $gradebookEntry->delete();

        return $this->respondNoContent();
    }

    /**
     * Save a whole marking sheet in one request; invalid rows are skipped.
     */
    public function bulkSave(BulkGradebookRequest $request, BulkGradebookAction $service): JsonResponse
    {
        $result = $service->save($request->validated()['entries'], $request->user());

        return response()->json(['data' => $result]);
    }

    /**
     * Shift every exam score in a section by a fixed number of points.
     */
    public function curve(CurveGradebookRequest $request, BulkGradebookAction $service): JsonResponse
    {
        $data = $request->validated();
        $section = CourseSection::query()->findOrFail($data['course_section_id']);
        $this->authorize('update', $section);

        $result = $service->curve(
            $section,
            (int) $data['points'],
            $data['term_id'] ?? null,
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }
}
