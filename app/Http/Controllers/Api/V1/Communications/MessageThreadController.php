<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Domains\Communications\Services\BulkCommunicationsAction;
use App\Domains\Communications\Services\MarkThreadRead;
use App\Domains\Communications\Services\OpenMessageThread;
use App\Domains\Communications\Services\ReplyToThread;
use App\Domains\Communications\Services\SetThreadStatus;
use App\Enums\MessageThreadStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Communications\BulkThreadsRequest;
use App\Http\Requests\Api\V1\Communications\ReplyToThreadRequest;
use App\Http\Requests\Api\V1\Communications\SetThreadStatusRequest;
use App\Http\Requests\Api\V1\Communications\StoreMessageThreadRequest;
use App\Http\Resources\Api\V1\Communications\MessageThreadResource;
use App\Http\Resources\Api\V1\Communications\ThreadMessageResource;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageThreadController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MessageThread::class);

        $query = MessageThread::query()->with(['participants']);
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($studentId = $request->string('student_id')->toString()) {
            $query->where('student_id', $studentId);
        }
        if ($request->boolean('unread')) {
            $query->where('unread_count', '>', 0);
        }
        if ($q = mb_trim($request->string('q')->toString())) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('subject', 'like', "%{$q}%")
                    ->orWhere('student_name', 'like', "%{$q}%")
                    ->orWhere('last_message_preview', 'like', "%{$q}%")
                    ->orWhereHas('participants', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        $paginator = $query->orderByDesc('last_message_at')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            MessageThreadResource::collection($paginator),
            $paginator,
        );
    }

    public function show(MessageThread $messageThread): JsonResponse
    {
        $this->authorize('view', $messageThread);
        $messageThread->load(['participants', 'messages']);

        return $this->respond(new MessageThreadResource($messageThread));
    }

    public function store(StoreMessageThreadRequest $request, OpenMessageThread $service): JsonResponse
    {
        $thread = $service->handle($request->validated());
        $thread->load(['participants', 'messages']);

        return $this->respondCreated(new MessageThreadResource($thread));
    }

    public function reply(ReplyToThreadRequest $request, MessageThread $messageThread, ReplyToThread $service): JsonResponse
    {
        $msg = $service->handle($messageThread, (string) $request->validated('body'), $request->user());

        return $this->respondCreated(new ThreadMessageResource($msg));
    }

    public function setStatus(SetThreadStatusRequest $request, MessageThread $messageThread, SetThreadStatus $service): JsonResponse
    {
        $thread = $service->handle(
            $messageThread,
            MessageThreadStatus::from((string) $request->validated('status')),
        );
        $thread->load(['participants', 'messages']);

        return $this->respond(new MessageThreadResource($thread));
    }

    public function markRead(MessageThread $messageThread, MarkThreadRead $service): JsonResponse
    {
        $this->authorize('update', $messageThread);
        $thread = $service->handle($messageThread);
        $thread->load(['participants', 'messages']);

        return $this->respond(new MessageThreadResource($thread));
    }

    /** POST /communications/threads/bulk — resolve | snooze | reopen | mark_read. */
    public function bulk(BulkThreadsRequest $request, BulkCommunicationsAction $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $service->threads($data['ids'], $data['action']),
        ]);
    }
}
