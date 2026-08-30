<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Resources\Api\V1\Identity\AuditEventResource;
use App\Models\AuditEvent;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only activity log for the active tenant. Audit rows are immutable —
 * there is no store/update/destroy on purpose.
 */
final class AuditEventController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditEvent::class);

        $query = AuditEvent::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->when($request->filled('name'), fn ($q) => $q->where('name', $request->string('name')))
            ->when($request->filled('domain'), fn ($q) => $q->forDomain((string) $request->string('domain')))
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->string('actor_id')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->string('subject_id')))
            ->when($request->filled('since'), fn ($q) => $q->where('occurred_at', '>=', $request->date('since')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $needle = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($needle): void {
                    $inner->where('summary', 'like', $needle)
                        ->orWhere('subject_label', 'like', $needle)
                        ->orWhere('name', 'like', $needle);
                });
            })
            ->orderByDesc('occurred_at');

        $paginator = $query->paginate((int) $request->integer('per_page', 50));

        return $this->respondPaginated(
            AuditEventResource::collection($paginator),
            $paginator,
        );
    }
}
