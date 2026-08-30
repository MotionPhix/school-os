<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Services\CreateTenant;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\StoreTenantRequest;
use App\Http\Requests\Api\V1\Identity\UpdateTenantRequest;
use App\Http\Resources\Api\V1\Identity\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        // Members see only their own tenants; platform admin sees all.
        $canSeeAll = $request->user()->can('viewAll', Tenant::class);

        $query = Tenant::query()->withCount(['users', 'campuses' => fn ($q) => $q->withoutGlobalScopes()]);
        if (! $canSeeAll) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id));
        }

        $paginator = $query->orderBy('name')->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            TenantResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);
        $tenant->loadCount(['users', 'campuses' => fn ($q) => $q->withoutGlobalScopes()]);

        return $this->respond(new TenantResource($tenant));
    }

    public function store(StoreTenantRequest $request, CreateTenant $service): JsonResponse
    {
        $tenant = $service->handle($request->validated(), $request->user());
        $tenant->loadCount(['users', 'campuses' => fn ($q) => $q->withoutGlobalScopes()]);

        return $this->respondCreated(new TenantResource($tenant));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->update($request->validated());
        $tenant->loadCount(['users', 'campuses' => fn ($q) => $q->withoutGlobalScopes()]);

        return $this->respond(new TenantResource($tenant));
    }
}
