<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\People;

use App\Domains\People\Services\BulkPeopleAction;
use App\Domains\People\Services\IssuePortalAccess;
use App\Domains\People\Services\LinkStudentGuardian;
use App\Domains\People\Services\PersonMediaService;
use App\Domains\People\Services\WriteGuardian;
use App\Enums\GuardianStatus;
use App\Enums\PersonSubject;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\People\BulkGuardianRequest;
use App\Http\Requests\Api\V1\People\LinkGuardianRequest;
use App\Http\Requests\Api\V1\People\SetGuardianPortalStatusRequest;
use App\Http\Requests\Api\V1\People\StoreGuardianRequest;
use App\Http\Requests\Api\V1\People\UpdateGuardianRequest;
use App\Http\Requests\Api\V1\People\UploadAvatarRequest;
use App\Http\Requests\Api\V1\People\UploadDocumentRequest;
use App\Http\Resources\Api\V1\People\GuardianResource;
use App\Http\Resources\Api\V1\People\PersonDocumentResource;
use App\Http\Resources\Api\V1\People\StudentResource;
use App\Models\Guardian;
use App\Models\PersonDocument;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuardianController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Guardian::class);

        $query = Guardian::query();
        if ($search = $request->string('search')->toString()) {
            $query->where('full_name', 'like', "%{$search}%")
                ->orWhere('contact_email', 'like', "%{$search}%");
        }
        if ($status = $request->string('portal_status')->toString()) {
            $query->where('portal_status', $status);
        }

        $paginator = $query
            ->orderBy('full_name')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            GuardianResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Guardian $guardian): JsonResponse
    {
        $this->authorize('view', $guardian);
        $guardian->load(['students', 'documents']);

        return $this->respond(new GuardianResource($guardian));
    }

    public function store(StoreGuardianRequest $request, WriteGuardian $service): JsonResponse
    {
        $guardian = $service->handle($request->validated());
        $guardian->load(['students', 'documents']);

        return $this->respondCreated(new GuardianResource($guardian));
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian, WriteGuardian $service): JsonResponse
    {
        $guardian = $service->handle($request->validated(), $guardian);
        $guardian->load(['students', 'documents']);

        return $this->respond(new GuardianResource($guardian));
    }

    public function destroy(Guardian $guardian): JsonResponse
    {
        $this->authorize('delete', $guardian);
        $guardian->delete();

        return $this->respondNoContent();
    }

    public function invite(Guardian $guardian, IssuePortalAccess $portal, Request $request): JsonResponse
    {
        $this->authorize('update', $guardian);
        $guardian = $portal->inviteGuardian($guardian, $request->user());
        $guardian->load(['students', 'documents']);

        return $this->respond(new GuardianResource($guardian));
    }

    public function setPortalStatus(SetGuardianPortalStatusRequest $request, Guardian $guardian, IssuePortalAccess $portal): JsonResponse
    {
        $guardian = $portal->setGuardianPortalStatus($guardian, GuardianStatus::from($request->validated('status')));
        $guardian->load(['students', 'documents']);

        return $this->respond(new GuardianResource($guardian));
    }

    public function bulk(BulkGuardianRequest $request, BulkPeopleAction $service): JsonResponse
    {
        $data = $request->validated();

        $result = $data['action'] === 'resend_invite'
            ? $service->resendGuardianInvites($data['ids'], $request->user())
            : $service->guardianPortalStatus($data['ids'], GuardianStatus::from($data['status']));

        return $this->respond($result);
    }

    public function link(
        LinkGuardianRequest $request,
        Student $student,
        Guardian $guardian,
        LinkStudentGuardian $service,
    ): JsonResponse {
        $service->link(
            $student,
            $guardian,
            (string) $request->validated('relationship'),
            (bool) ($request->validated('is_primary') ?? false),
        );
        $student->load(['guardians']);

        return $this->respond(new StudentResource($student));
    }

    public function unlink(Student $student, Guardian $guardian, LinkStudentGuardian $service): JsonResponse
    {
        $this->authorize('update', $student);
        $this->authorize('update', $guardian);
        $service->unlink($student, $guardian);
        $student->load(['guardians']);

        return $this->respond(new StudentResource($student));
    }

    public function uploadAvatar(UploadAvatarRequest $request, Guardian $guardian, PersonMediaService $media): JsonResponse
    {
        $this->authorize('update', $guardian);
        $media->setAvatar(PersonSubject::Guardians, $guardian, $request->file('file'));
        $guardian->load(['students', 'documents']);

        return $this->respond(new GuardianResource($guardian));
    }

    public function uploadDocument(UploadDocumentRequest $request, Guardian $guardian, PersonMediaService $media): JsonResponse
    {
        $this->authorize('create', [PersonDocument::class, PersonSubject::Guardians->value]);
        $document = $media->attachDocument(
            PersonSubject::Guardians,
            $guardian,
            $request->file('file'),
            (string) $request->validated('name'),
            $request->user(),
        );

        return $this->respondCreated(new PersonDocumentResource($document));
    }

    public function destroyDocument(Guardian $guardian, PersonDocument $document, PersonMediaService $media): JsonResponse
    {
        abort_unless($document->subject_type === PersonSubject::Guardians->value && $document->subject_id === $guardian->id, 404);
        $this->authorize('delete', $document);
        $media->removeDocument($document);

        return $this->respondNoContent();
    }
}
