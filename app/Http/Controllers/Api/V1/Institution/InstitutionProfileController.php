<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Institution;

use App\Domains\Institution\Services\SetInstitutionLogo;
use App\Domains\Institution\Services\UpsertInstitutionProfile;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Institution\UploadInstitutionLogoRequest;
use App\Http\Requests\Api\V1\Institution\UpsertInstitutionProfileRequest;
use App\Http\Resources\Api\V1\Institution\InstitutionProfileResource;
use App\Models\InstitutionProfile;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

final class InstitutionProfileController extends CapabilityController
{
    public function show(TenantContext $tenants): JsonResponse
    {
        $this->authorize('view', InstitutionProfile::class);

        $profile = InstitutionProfile::query()
            ->where('tenant_id', $tenants->id())
            ->firstOrFail();

        return $this->respond(new InstitutionProfileResource($profile));
    }

    public function update(UpsertInstitutionProfileRequest $request, UpsertInstitutionProfile $service): JsonResponse
    {
        $profile = $service->handle($request->validated());

        return $this->respond(new InstitutionProfileResource($profile));
    }

    public function uploadLogo(UploadInstitutionLogoRequest $request, SetInstitutionLogo $service): JsonResponse
    {
        $profile = $service->store($request->file('logo'));

        return $this->respond(new InstitutionProfileResource($profile));
    }

    public function destroyLogo(SetInstitutionLogo $service): JsonResponse
    {
        $this->authorize('update', InstitutionProfile::class);

        return $this->respond(new InstitutionProfileResource($service->clear()));
    }
}
