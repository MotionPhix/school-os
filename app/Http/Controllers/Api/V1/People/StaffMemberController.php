<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\People;

use App\Domains\People\Services\BulkPeopleAction;
use App\Domains\People\Services\IssuePortalAccess;
use App\Domains\People\Services\PersonMediaService;
use App\Domains\People\Services\WriteStaffMember;
use App\Enums\PersonSubject;
use App\Enums\StaffStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\People\BulkStaffMemberRequest;
use App\Http\Requests\Api\V1\People\SetStaffStatusRequest;
use App\Http\Requests\Api\V1\People\StoreStaffMemberRequest;
use App\Http\Requests\Api\V1\People\UpdateStaffMemberRequest;
use App\Http\Requests\Api\V1\People\UploadAvatarRequest;
use App\Http\Requests\Api\V1\People\UploadDocumentRequest;
use App\Http\Resources\Api\V1\People\PersonDocumentResource;
use App\Http\Resources\Api\V1\People\StaffMemberResource;
use App\Models\PersonDocument;
use App\Models\StaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffMemberController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StaffMember::class);

        $query = StaffMember::query()->with(['campus']);
        if ($campusId = $request->string('campus_id')->toString()) {
            $query->where('campus_id', $campusId);
        }
        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('staff_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy('full_name')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            StaffMemberResource::collection($paginator),
            $paginator,
        );
    }

    public function show(StaffMember $staffMember): JsonResponse
    {
        $this->authorize('view', $staffMember);
        $staffMember->load(['campus', 'documents']);

        return $this->respond(new StaffMemberResource($staffMember));
    }

    public function store(StoreStaffMemberRequest $request, WriteStaffMember $service): JsonResponse
    {
        $staff = $service->handle($request->validated());
        $staff->load(['campus', 'documents']);

        return $this->respondCreated(new StaffMemberResource($staff));
    }

    public function update(UpdateStaffMemberRequest $request, StaffMember $staffMember, WriteStaffMember $service): JsonResponse
    {
        $staff = $service->handle($request->validated(), $staffMember);
        $staff->load(['campus', 'documents']);

        return $this->respond(new StaffMemberResource($staff));
    }

    public function setStatus(SetStaffStatusRequest $request, StaffMember $staffMember, WriteStaffMember $service): JsonResponse
    {
        $staff = $service->setStatus($staffMember, StaffStatus::from($request->validated('status')));
        $staff->load(['campus', 'documents']);

        return $this->respond(new StaffMemberResource($staff));
    }

    public function issueLogin(StaffMember $staffMember, IssuePortalAccess $portal, Request $request): JsonResponse
    {
        $this->authorize('update', $staffMember);
        $staff = $portal->issueStaffLogin($staffMember, $request->user());
        $staff->load(['campus', 'documents']);

        return $this->respond(new StaffMemberResource($staff));
    }

    public function revokeLogin(StaffMember $staffMember, IssuePortalAccess $portal): JsonResponse
    {
        $this->authorize('update', $staffMember);
        $staff = $portal->revokeStaffLogin($staffMember);
        $staff->load(['campus', 'documents']);

        return $this->respond(new StaffMemberResource($staff));
    }

    public function bulk(BulkStaffMemberRequest $request, BulkPeopleAction $service): JsonResponse
    {
        $data = $request->validated();

        $result = $data['action'] === 'issue_login'
            ? $service->issueStaffLogins($data['ids'], $request->user())
            : $service->staffStatus($data['ids'], StaffStatus::from($data['status']));

        return $this->respond($result);
    }

    public function destroy(StaffMember $staffMember): JsonResponse
    {
        $this->authorize('delete', $staffMember);
        $staffMember->delete();

        return $this->respondNoContent();
    }

    public function uploadAvatar(UploadAvatarRequest $request, StaffMember $staffMember, PersonMediaService $media): JsonResponse
    {
        $this->authorize('update', $staffMember);
        $media->setAvatar(PersonSubject::Staff, $staffMember, $request->file('file'));
        $staffMember->load(['campus', 'documents']);

        return $this->respond(new StaffMemberResource($staffMember));
    }

    public function uploadDocument(UploadDocumentRequest $request, StaffMember $staffMember, PersonMediaService $media): JsonResponse
    {
        $this->authorize('create', [PersonDocument::class, PersonSubject::Staff->value]);
        $document = $media->attachDocument(
            PersonSubject::Staff,
            $staffMember,
            $request->file('file'),
            (string) $request->validated('name'),
            $request->user(),
        );

        return $this->respondCreated(new PersonDocumentResource($document));
    }

    public function destroyDocument(StaffMember $staffMember, PersonDocument $document, PersonMediaService $media): JsonResponse
    {
        abort_unless($document->subject_type === PersonSubject::Staff->value && $document->subject_id === $staffMember->id, 404);
        $this->authorize('delete', $document);
        $media->removeDocument($document);

        return $this->respondNoContent();
    }
}
