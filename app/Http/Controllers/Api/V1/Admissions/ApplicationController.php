<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admissions;

use App\Domains\Admissions\Services\AdvanceApplicationStage;
use App\Domains\Admissions\Services\BulkAdmissionsAction;
use App\Domains\Admissions\Services\EnrollApplication;
use App\Domains\Admissions\Services\RecordAssessmentScores;
use App\Domains\Admissions\Services\RespondToOffer;
use App\Domains\Admissions\Services\SendOffer;
use App\Domains\Admissions\Services\WriteApplication;
use App\Enums\OfferStatus;
use App\Enums\PipelineStage;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Admissions\AdvanceStageRequest;
use App\Http\Requests\Api\V1\Admissions\BulkApplicationsRequest;
use App\Http\Requests\Api\V1\Admissions\EnrollApplicationRequest;
use App\Http\Requests\Api\V1\Admissions\RecordScoresRequest;
use App\Http\Requests\Api\V1\Admissions\RespondOfferRequest;
use App\Http\Requests\Api\V1\Admissions\SendOfferRequest;
use App\Http\Requests\Api\V1\Admissions\StoreApplicationRequest;
use App\Http\Requests\Api\V1\Admissions\UpdateApplicationRequest;
use App\Http\Resources\Api\V1\Admissions\ApplicationResource;
use App\Http\Resources\Api\V1\People\StudentResource;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApplicationController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Application::class);

        $query = Application::query()->with(['campus', 'academicYear', 'currentOffer']);

        if ($stage = $request->string('stage')->toString()) {
            $query->where('stage', $stage);
        }
        if ($campusId = $request->string('campus_id')->toString()) {
            $query->where('campus_id', $campusId);
        }
        if ($yearId = $request->string('academic_year_id')->toString()) {
            $query->where('academic_year_id', $yearId);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('applicant_full_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('guardian_name', 'like', "%{$search}%");
            });
        }
        if ($request->boolean('open_only')) {
            $query->open();
        }

        $paginator = $query
            ->orderByDesc('submitted_at')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            ApplicationResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Application $application): JsonResponse
    {
        $this->authorize('view', $application);
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respond(new ApplicationResource($application));
    }

    public function store(StoreApplicationRequest $request, WriteApplication $service): JsonResponse
    {
        $application = $service->handle($request->validated(), null, $request->user());
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respondCreated(new ApplicationResource($application));
    }

    public function update(UpdateApplicationRequest $request, Application $application, WriteApplication $service): JsonResponse
    {
        $application = $service->handle($request->validated(), $application, $request->user());
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respond(new ApplicationResource($application));
    }

    public function destroy(Application $application): JsonResponse
    {
        $this->authorize('delete', $application);
        $application->delete();

        return $this->respondNoContent();
    }

    public function advance(AdvanceStageRequest $request, Application $application, AdvanceApplicationStage $service): JsonResponse
    {
        $application = $service->handle(
            $application,
            PipelineStage::from($request->string('to_stage')->toString()),
            $request->string('note')->toString() ?: null,
            $request->user(),
        );
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respond(new ApplicationResource($application));
    }

    public function sendOffer(SendOfferRequest $request, Application $application, SendOffer $service): JsonResponse
    {
        $application = $service->handle($application, $request->validated(), $request->user());
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respond(new ApplicationResource($application));
    }

    public function respondOffer(RespondOfferRequest $request, Application $application, RespondToOffer $service): JsonResponse
    {
        $application = $service->handle(
            $application,
            OfferStatus::from($request->string('response')->toString()),
            $request->user(),
        );
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respond(new ApplicationResource($application));
    }

    public function enroll(EnrollApplicationRequest $request, Application $application, EnrollApplication $service): JsonResponse
    {
        $student = $service->handle(
            $application,
            $request->user(),
            $request->string('admission_number')->toString() ?: null,
        );

        return $this->respondCreated(new StudentResource($student));
    }

    public function recordScores(RecordScoresRequest $request, Application $application, RecordAssessmentScores $service): JsonResponse
    {
        $application = $service->handle($application, $request->scores(), $request->user());
        $application->load(['campus', 'academicYear', 'currentOffer', 'timeline']);

        return $this->respond(new ApplicationResource($application));
    }

    /**
     * Batch pipeline operations from the applications table. Returns the
     * per-row skip reasons so the UI can surface partial success.
     */
    public function bulk(BulkApplicationsRequest $request, BulkAdmissionsAction $service): JsonResponse
    {
        $data = $request->validated();
        $ids = $data['ids'];
        $note = $data['note'] ?? null;
        $actor = $request->user();

        $result = match ($data['action']) {
            'advance_stage' => $service->advanceStage($ids, PipelineStage::from($data['to_stage']), $note, $actor),
            'reject' => $service->reject($ids, $note, $actor),
            'withdraw' => $service->withdraw($ids, $note, $actor),
        };

        return $this->respond($result);
    }
}
