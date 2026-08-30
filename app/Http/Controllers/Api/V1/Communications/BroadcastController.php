<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Domains\Communications\Services\BulkCommunicationsAction;
use App\Domains\Communications\Services\CancelBroadcast;
use App\Domains\Communications\Services\CompleteBroadcast;
use App\Domains\Communications\Services\DuplicateBroadcast;
use App\Domains\Communications\Services\StartBroadcast;
use App\Domains\Communications\Services\WriteBroadcast;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Communications\BulkBroadcastsRequest;
use App\Http\Requests\Api\V1\Communications\StoreBroadcastRequest;
use App\Http\Resources\Api\V1\Communications\BroadcastResource;
use App\Models\Broadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BroadcastController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        $query = Broadcast::query();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($channel = $request->string('channel')->toString()) {
            $query->where('channel', $channel);
        }
        if ($audience = $request->string('audience')->toString()) {
            $query->where('audience', $audience);
        }
        if ($q = mb_trim($request->string('q')->toString())) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('template_snippet', 'like', "%{$q}%")
                    ->orWhere('audience_label', 'like', "%{$q}%");
            });
        }

        $paginator = $query
            ->orderByRaw('COALESCE(scheduled_for, completed_at, created_at) DESC')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            BroadcastResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Broadcast $broadcast): JsonResponse
    {
        $this->authorize('view', $broadcast);

        return $this->respond(new BroadcastResource($broadcast));
    }

    public function store(StoreBroadcastRequest $request, WriteBroadcast $service): JsonResponse
    {
        $b = $service->create($request->validated(), $request->user());

        return $this->respondCreated(new BroadcastResource($b));
    }

    public function start(Broadcast $broadcast, StartBroadcast $service): JsonResponse
    {
        $this->authorize('start', $broadcast);

        return $this->respond(new BroadcastResource($service->handle($broadcast)));
    }

    public function cancel(Broadcast $broadcast, CancelBroadcast $service): JsonResponse
    {
        $this->authorize('cancel', $broadcast);

        return $this->respond(new BroadcastResource($service->handle($broadcast)));
    }

    /** POST /communications/broadcasts/{broadcast}/complete — settle a sending campaign. */
    public function complete(Broadcast $broadcast, CompleteBroadcast $service): JsonResponse
    {
        $this->authorize('start', $broadcast);

        return $this->respond(new BroadcastResource($service->handle($broadcast)));
    }

    /** POST /communications/broadcasts/{broadcast}/duplicate — clone back to draft. */
    public function duplicate(Request $request, Broadcast $broadcast, DuplicateBroadcast $service): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        return $this->respondCreated(new BroadcastResource($service->handle($broadcast, $request->user())));
    }

    /** POST /communications/broadcasts/bulk — start | cancel | delete. */
    public function bulk(BulkBroadcastsRequest $request, BulkCommunicationsAction $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $service->broadcasts($data['ids'], $data['action'], $request->user()),
        ]);
    }
}
