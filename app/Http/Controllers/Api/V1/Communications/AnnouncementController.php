<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Domains\Communications\Services\ArchiveAnnouncement;
use App\Domains\Communications\Services\BulkCommunicationsAction;
use App\Domains\Communications\Services\SendAnnouncement;
use App\Domains\Communications\Services\UnscheduleAnnouncement;
use App\Domains\Communications\Services\WriteAnnouncement;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Communications\BulkAnnouncementsRequest;
use App\Http\Requests\Api\V1\Communications\StoreAnnouncementRequest;
use App\Http\Requests\Api\V1\Communications\UpdateAnnouncementRequest;
use App\Http\Resources\Api\V1\Communications\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnnouncementController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($audience = $request->string('audience')->toString()) {
            $query->where('audience', $audience);
        }
        if ($channel = $request->string('channel')->toString()) {
            $query->whereJsonContains('channels', $channel);
        }
        if ($from = $request->string('sent_from')->toString()) {
            $query->whereDate('sent_at', '>=', $from);
        }
        if ($to = $request->string('sent_to')->toString()) {
            $query->whereDate('sent_at', '<=', $to);
        }
        if ($q = mb_trim($request->string('q')->toString())) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%")
                    ->orWhere('audience_label', 'like', "%{$q}%")
                    ->orWhere('author_name', 'like', "%{$q}%");
            });
        }

        $paginator = $query
            ->orderByRaw('COALESCE(sent_at, scheduled_for, created_at) DESC')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            AnnouncementResource::collection($paginator),
            $paginator,
        );
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        return $this->respond(new AnnouncementResource($announcement));
    }

    public function store(StoreAnnouncementRequest $request, WriteAnnouncement $service): JsonResponse
    {
        $ann = $service->create($request->validated(), $request->user());

        return $this->respondCreated(new AnnouncementResource($ann));
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement, WriteAnnouncement $service): JsonResponse
    {
        $ann = $service->update($announcement, $request->validated());

        return $this->respond(new AnnouncementResource($ann));
    }

    public function send(Announcement $announcement, SendAnnouncement $service): JsonResponse
    {
        $this->authorize('send', $announcement);
        $ann = $service->handle($announcement);

        return $this->respond(new AnnouncementResource($ann));
    }

    public function archive(Announcement $announcement, ArchiveAnnouncement $service): JsonResponse
    {
        $this->authorize('archive', $announcement);
        $ann = $service->handle($announcement);

        return $this->respond(new AnnouncementResource($ann));
    }

    /** POST /communications/announcements/{announcement}/unschedule */
    public function unschedule(Announcement $announcement, UnscheduleAnnouncement $service): JsonResponse
    {
        $this->authorize('update', $announcement);

        return $this->respond(new AnnouncementResource($service->handle($announcement)));
    }

    /** POST /communications/announcements/bulk — send | archive | unschedule | delete. */
    public function bulk(BulkAnnouncementsRequest $request, BulkCommunicationsAction $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $service->announcements($data['ids'], $data['action']),
        ]);
    }
}
