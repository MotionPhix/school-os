<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\MessageThreadReplied;
use App\Enums\ThreadParticipantRole;
use App\Models\MessageThread;
use App\Models\ThreadMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Append a staff reply to a thread. Denormalises last_message_preview /
 * last_message_at on the parent thread so the inbox list stays a
 * single-query read. Marks the thread's messages read from the author's
 * side (unread_count tracks the *other* party's inbox).
 */
final class ReplyToThread
{
    public function handle(MessageThread $thread, string $body, User $actor): ThreadMessage
    {
        return DB::transaction(function () use ($thread, $body, $actor) {
            $msg = new ThreadMessage;
            $msg->fill([
                'tenant_id' => $thread->tenant_id,
                'thread_id' => $thread->id,
                'author_id' => $actor->id,
                'author_name' => $actor->name,
                'author_role' => ThreadParticipantRole::Staff->value,
                'body' => $body,
                'sent_at' => now(),
                'read' => true,
            ]);
            $msg->save();

            $thread->last_message_preview = mb_substr($body, 0, 120);
            $thread->last_message_at = $msg->sent_at;
            $thread->unread_count = 0;
            $thread->save();

            MessageThreadReplied::dispatch($msg);

            return $msg->refresh();
        });
    }
}
