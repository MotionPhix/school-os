<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\People;

use App\Domains\People\Services\BulkPeopleAction;
use App\Domains\People\Services\PersonMediaService;
use App\Domains\People\Services\WriteStudent;
use App\Enums\PersonSubject;
use App\Enums\StudentStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\People\BulkStudentRequest;
use App\Http\Requests\Api\V1\People\SetStudentStatusRequest;
use App\Http\Requests\Api\V1\People\StoreStudentRequest;
use App\Http\Requests\Api\V1\People\TransferStudentCampusRequest;
use App\Http\Requests\Api\V1\People\UpdateStudentRequest;
use App\Http\Requests\Api\V1\People\UploadAvatarRequest;
use App\Http\Requests\Api\V1\People\UploadDocumentRequest;
use App\Http\Resources\Api\V1\People\PersonDocumentResource;
use App\Http\Resources\Api\V1\People\StudentResource;
use App\Models\Campus;
use App\Models\PersonDocument;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::query()->with(['campus'])->withMetrics();

        if ($campusId = $request->string('campus_id')->toString()) {
            $query->where('campus_id', $campusId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy('full_name')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            StudentResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Student $student): JsonResponse
    {
        $this->authorize('view', $student);
        $student->load(['campus', 'guardians', 'documents']);
        $student->loadMetrics();

        return $this->respond(new StudentResource($student));
    }

    public function store(StoreStudentRequest $request, WriteStudent $service): JsonResponse
    {
        $student = $service->handle($request->validated());
        $student->load(['campus', 'guardians', 'documents']);
        $student->loadMetrics();

        return $this->respondCreated(new StudentResource($student));
    }

    public function update(UpdateStudentRequest $request, Student $student, WriteStudent $service): JsonResponse
    {
        $student = $service->handle($request->validated(), $student);
        $student->load(['campus', 'guardians', 'documents']);
        $student->loadMetrics();

        return $this->respond(new StudentResource($student));
    }

    public function setStatus(SetStudentStatusRequest $request, Student $student, WriteStudent $service): JsonResponse
    {
        $student = $service->setStatus($student, StudentStatus::from($request->validated('status')));
        $student->load(['campus', 'guardians', 'documents']);
        $student->loadMetrics();

        return $this->respond(new StudentResource($student));
    }

    public function transfer(TransferStudentCampusRequest $request, Student $student, WriteStudent $service): JsonResponse
    {
        $campus = Campus::query()->findOrFail($request->validated('campus_id'));
        $student = $service->handle(['campus_id' => $campus->id], $student);
        $student->load(['campus', 'guardians', 'documents']);
        $student->loadMetrics();

        return $this->respond(new StudentResource($student));
    }

    public function bulk(BulkStudentRequest $request, BulkPeopleAction $service): JsonResponse
    {
        $data = $request->validated();

        $result = $data['action'] === 'transfer_campus'
            ? $service->transferStudents($data['ids'], Campus::query()->findOrFail($data['campus_id']))
            : $service->studentStatus($data['ids'], StudentStatus::from($data['status']));

        return $this->respond($result);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('delete', $student);
        $student->delete();

        return $this->respondNoContent();
    }

    public function uploadAvatar(UploadAvatarRequest $request, Student $student, PersonMediaService $media): JsonResponse
    {
        $this->authorize('update', $student);
        $media->setAvatar(PersonSubject::Students, $student, $request->file('file'));
        $student->load(['campus', 'guardians', 'documents']);
        $student->loadMetrics();

        return $this->respond(new StudentResource($student));
    }

    public function uploadDocument(UploadDocumentRequest $request, Student $student, PersonMediaService $media): JsonResponse
    {
        $this->authorize('create', [PersonDocument::class, PersonSubject::Students->value]);
        $document = $media->attachDocument(
            PersonSubject::Students,
            $student,
            $request->file('file'),
            (string) $request->validated('name'),
            $request->user(),
        );

        return $this->respondCreated(new PersonDocumentResource($document));
    }

    public function destroyDocument(Student $student, PersonDocument $document, PersonMediaService $media): JsonResponse
    {
        abort_unless($document->subject_type === PersonSubject::Students->value && $document->subject_id === $student->id, 404);
        $this->authorize('delete', $document);
        $media->removeDocument($document);

        return $this->respondNoContent();
    }
}
