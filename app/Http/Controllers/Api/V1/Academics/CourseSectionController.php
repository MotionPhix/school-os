<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academics;

use App\Domains\Academics\Services\BulkAcademicsAction;
use App\Domains\Academics\Services\EnrollStudentInCourse;
use App\Domains\Academics\Services\WriteCourseSection;
use App\Enums\CourseStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Academics\BulkCourseSectionsRequest;
use App\Http\Requests\Api\V1\Academics\DuplicateCourseSectionRequest;
use App\Http\Requests\Api\V1\Academics\EnrollStudentRequest;
use App\Http\Requests\Api\V1\Academics\StoreCourseSectionRequest;
use App\Http\Requests\Api\V1\Academics\UpdateCourseSectionRequest;
use App\Http\Resources\Api\V1\Academics\CourseSectionResource;
use App\Http\Resources\Api\V1\People\StudentResource;
use App\Models\CourseSection;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CourseSectionController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CourseSection::class);

        $query = CourseSection::query()
            ->with(['subject', 'academicYear', 'campus', 'teacher'])
            ->withCount('students');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($yearId = $request->string('academic_year_id')->toString()) {
            $query->where('academic_year_id', $yearId);
        }
        if ($campusId = $request->string('campus_id')->toString()) {
            $query->where('campus_id', $campusId);
        }
        if ($subjectId = $request->string('subject_id')->toString()) {
            $query->where('subject_id', $subjectId);
        }
        if ($teacherId = $request->string('teacher_id')->toString()) {
            $query->where('teacher_id', $teacherId);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('grade_label', 'like', "%{$search}%")
                    ->orWhere('section_label', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy('grade_label')
            ->orderBy('section_label')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            CourseSectionResource::collection($paginator),
            $paginator,
        );
    }

    public function show(CourseSection $courseSection): JsonResponse
    {
        $this->authorize('view', $courseSection);
        $courseSection->load(['subject', 'academicYear', 'campus', 'teacher'])->loadCount('students');

        return $this->respond(new CourseSectionResource($courseSection));
    }

    public function store(StoreCourseSectionRequest $request, WriteCourseSection $service): JsonResponse
    {
        $section = $service->handle($request->validated());
        $section->load(['subject', 'academicYear', 'campus', 'teacher'])->loadCount('students');

        return $this->respondCreated(new CourseSectionResource($section));
    }

    public function update(
        UpdateCourseSectionRequest $request,
        CourseSection $courseSection,
        WriteCourseSection $service,
        BulkAcademicsAction $guard,
    ): JsonResponse {
        $data = $request->validated();

        if (isset($data['status'])) {
            $guard->assertPublishable($courseSection, CourseStatus::from($data['status']));
        }

        $section = $service->handle($data, $courseSection);
        $section->load(['subject', 'academicYear', 'campus', 'teacher'])->loadCount('students');

        return $this->respond(new CourseSectionResource($section));
    }

    public function destroy(CourseSection $courseSection): JsonResponse
    {
        $this->authorize('delete', $courseSection);
        $courseSection->delete();

        return $this->respondNoContent();
    }

    public function roster(CourseSection $courseSection): JsonResponse
    {
        $this->authorize('view', $courseSection);
        $students = $courseSection->students()->orderBy('full_name')->get();

        return response()->json([
            'data' => StudentResource::collection($students)->resolve(),
        ]);
    }

    public function enroll(EnrollStudentRequest $request, CourseSection $courseSection, EnrollStudentInCourse $service): JsonResponse
    {
        $student = Student::query()->findOrFail($request->string('student_id')->toString());
        $service->enroll($courseSection, $student);

        return $this->respond(new StudentResource($student), 201);
    }

    public function drop(CourseSection $courseSection, Student $student, EnrollStudentInCourse $service): JsonResponse
    {
        $this->authorize('update', $courseSection);
        $service->drop($courseSection, $student);

        return $this->respondNoContent();
    }

    /**
     * Batch lifecycle transitions and guarded deletion.
     */
    public function bulk(BulkCourseSectionsRequest $request, BulkAcademicsAction $service): JsonResponse
    {
        $data = $request->validated();
        $ids = $data['ids'];

        $result = match ($data['action']) {
            'publish' => $service->setCourseStatus($ids, CourseStatus::Published),
            'draft' => $service->setCourseStatus($ids, CourseStatus::Draft),
            'archive' => $service->setCourseStatus($ids, CourseStatus::Archived),
            'delete' => $service->deleteCourses($ids),
        };

        return response()->json(['data' => $result]);
    }

    public function duplicate(
        DuplicateCourseSectionRequest $request,
        CourseSection $courseSection,
        BulkAcademicsAction $service,
    ): JsonResponse {
        $copy = $service->duplicateCourse($courseSection, $request->string('section_label')->toString());
        $copy->load(['subject', 'academicYear', 'campus', 'teacher'])->loadCount('students');

        return $this->respondCreated(new CourseSectionResource($copy));
    }
}
