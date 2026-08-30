<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Base controller for capability endpoints.
 *
 * Enforces the response envelope the SchoolOS Presentation Contracts
 * expect: `{ data, meta?, links? }` for reads, `{ data, issued_at }`
 * for writes. Never return raw arrays from a capability controller.
 */
abstract class CapabilityController extends Controller
{
    use AuthorizesRequests;

    protected function respond(JsonResource $resource, int $status = 200): JsonResponse
    {
        return $resource->response()->setStatusCode($status);
    }

    protected function respondCreated(JsonResource $resource): JsonResponse
    {
        return $this->respond($resource, 201);
    }

    protected function respondPaginated(ResourceCollection $collection, LengthAwarePaginator $paginator): JsonResponse
    {
        return $collection->response()->setStatusCode(200)->setData([
            'data' => $collection->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function respondNoContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
