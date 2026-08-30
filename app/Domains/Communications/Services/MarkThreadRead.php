<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Models\MessageThread;
use App\Models\ThreadMessage;
use Illuminate\Support\Facades\DB;

final class MarkThreadRead
{
    public function handle(MessageThread $thread): MessageThread
    {
        return DB::transaction(function () use ($thread) {
            ThreadMessage::query()
                ->where('thread_id', $thread->id)
                ->where('read', false)
                ->update(['read' => true, 'updated_at' => now()]);

            $thread->unread_count = 0;
            $thread->save();

            return $thread->refresh();
        });
    }
}
