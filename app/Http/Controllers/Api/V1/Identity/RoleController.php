<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Services\WriteRole;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\StoreRoleRequest;
use App\Http\Requests\Api\V1\Identity\UpdateRoleRequest;
use App\Http\Resources\Api\V1\Identity\RoleResource;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $tenantId = app(TenantContext::class)->id();

        $roles = Role::query()->forTenant($tenantId)->orderBy('name')->get();

        // Compute member_count per role in a single pass over pivot rows.
        $memberships = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->get(['role_ids']);

        $counts = [];
        foreach ($memberships as $m) {
            foreach ($m->role_ids ?? [] as $rid) {
                $counts[$rid] = ($counts[$rid] ?? 0) + 1;
            }
        }
        $roles->each(function (Role $r) use ($counts): void {
            $r->member_count = $counts[$r->id] ?? 0;
        });

        return $this->respond(RoleResource::collection($roles));
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return $this->respond(new RoleResource($role));
    }

    public function store(StoreRoleRequest $request, WriteRole $service): JsonResponse
    {
        $data = $request->validated();
        // Tenant-scoped roles always belong to the caller's tenant; platform
        // roles can only be created by seeder/console, not the API.
        $data['tenant_id'] = app(TenantContext::class)->id();
        $role = $service->create($data);

        return $this->respondCreated(new RoleResource($role));
    }

    public function update(UpdateRoleRequest $request, Role $role, WriteRole $service): JsonResponse
    {
        $service->update($role, $request->validated());

        return $this->respond(new RoleResource($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);
        $role->delete();

        return $this->respondNoContent();
    }
}
