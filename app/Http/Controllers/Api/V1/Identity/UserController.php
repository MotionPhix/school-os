<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Services\AssignRoles;
use App\Domains\Identity\Services\SetUserStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\UpdateUserRolesRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $tenantId = app(TenantContext::class)->id();
        $paginator = User::query()
            ->inTenant($tenantId)
            ->with('memberships')
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            UserResource::collection($paginator),
            $paginator,
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $user->load('memberships');

        return $this->respond(new UserResource($user));
    }

    public function assignRoles(
        UpdateUserRolesRequest $request,
        User $user,
        AssignRoles $service,
    ): JsonResponse {
        $service->handle(
            $user,
            app(TenantContext::class)->id(),
            $request->validated('role_ids'),
            $request->user()->id,
        );

        $user->load('memberships');

        return $this->respond(new UserResource($user));
    }

    public function suspend(Request $request, User $user, SetUserStatus $service): JsonResponse
    {
        $this->authorize('suspend', $user);
        $service->suspend($user, app(TenantContext::class)->id(), $request->user()->id);
        $user->load('memberships');

        return $this->respond(new UserResource($user));
    }

    public function reactivate(Request $request, User $user, SetUserStatus $service): JsonResponse
    {
        $this->authorize('suspend', $user); // same gate: manage-users
        $service->reactivate($user, app(TenantContext::class)->id(), $request->user()->id);
        $user->load('memberships');

        return $this->respond(new UserResource($user));
    }
}
