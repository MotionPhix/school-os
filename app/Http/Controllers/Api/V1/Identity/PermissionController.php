<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Support\PermissionCatalog;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Resources\Api\V1\Identity\PermissionResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

final class PermissionController extends CapabilityController
{
    public function index(PermissionCatalog $catalog): JsonResponse
    {
        // The catalog describes the tenant's authorization surface; it is
        // read by the roles editor, so it rides on the same gate.
        $this->authorize('viewAny', Role::class);

        return $this->respond(PermissionResource::collection($catalog->all()));
    }
}
