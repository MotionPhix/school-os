<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\MessageThreadStatusChanged;
use App\Enums\MessageThreadStatus;
use App\Models\MessageThread;
use Illuminate\Support\Facades\DB;

final class SetThreadStatus
{
    public function handle(MessageThread $thread, MessageThreadStatus $status): MessageThread
    {
        if ($thread->status === $status) {
            return $thread;
        }

        return DB::transaction(function () use ($thread, $status) {
            $previous = $thread->status->value;
            $thread->status = $status;
            $thread->save();

            MessageThreadStatusChanged::dispatch($thread, $previous);

            return $thread->refresh();
        });
    }
}
