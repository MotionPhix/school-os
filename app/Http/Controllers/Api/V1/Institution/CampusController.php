<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Institution;

use App\Domains\Institution\Services\BulkCampusAction;
use App\Domains\Institution\Services\WriteCampus;
use App\Enums\CampusStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Institution\BulkCampusRequest;
use App\Http\Requests\Api\V1\Institution\StoreCampusRequest;
use App\Http\Requests\Api\V1\Institution\UpdateCampusRequest;
use App\Http\Resources\Api\V1\Institution\CampusResource;
use App\Models\Campus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CampusController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campus::class);

        $paginator = Campus::query()
            ->withCount([
                'students' => fn ($q) => $q->enrolled(),
                'staffMembers' => fn ($q) => $q->active(),
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim()->value().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhere('region', 'like', $term);
                });
            })
            ->when(
                $request->filled('status') && $request->string('status')->value() !== 'all',
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('primary') && $request->string('primary')->value() !== 'all',
                fn ($query) => $query->where('is_primary', $request->string('primary')->value() === 'primary'),
            )
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            CampusResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Campus $campus): JsonResponse
    {
        $this->authorize('view', $campus);
        $campus->loadCount([
            'students' => fn ($q) => $q->enrolled(),
            'staffMembers' => fn ($q) => $q->active(),
        ]);

        return $this->respond(new CampusResource($campus));
    }

    public function store(StoreCampusRequest $request, WriteCampus $service): JsonResponse
    {
        $campus = $service->handle($request->validated());

        return $this->respondCreated(new CampusResource($campus));
    }

    public function update(UpdateCampusRequest $request, Campus $campus, WriteCampus $service): JsonResponse
    {
        $campus = $service->handle($request->validated(), $campus);

        return $this->respond(new CampusResource($campus));
    }

    public function destroy(Campus $campus): JsonResponse
    {
        $this->authorize('delete', $campus);

        if ($campus->is_primary) {
            throw ValidationException::withMessages([
                'campus' => 'Set another campus as primary before deleting this one.',
            ]);
        }

        $campus->delete();

        return $this->respondNoContent();
    }

    /**
     * Bulk status change / delete for the hardened campuses table.
     * Illegal members of the selection are skipped and reported rather
     * than failing the whole batch.
     */
    public function bulk(BulkCampusRequest $request, BulkCampusAction $service): JsonResponse
    {
        $data = $request->validated();

        $result = $data['action'] === 'delete'
            ? $service->delete($data['ids'])
            : $service->setStatus($data['ids'], CampusStatus::from($data['status']));

        return $this->respond($result);
    }
}
