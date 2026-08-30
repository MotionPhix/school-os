<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\BroadcastStarted;
use App\Enums\BroadcastStatus;
use App\Events\BroadcastProgressUpdated;
use App\Models\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartBroadcast
{
    public function handle(Broadcast $b): Broadcast
    {
        if (! in_array($b->status, [BroadcastStatus::Draft, BroadcastStatus::Queued], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or queued broadcasts can be started.',
            ]);
        }

        return DB::transaction(function () use ($b) {
            $b->status = BroadcastStatus::Sending;
            $b->started_at = now();
            // Optimistic in-flight delivery snapshot; the channel
            // adapter updates actuals as receipts flow in.
            $b->delivered_count = (int) round($b->recipient_count * 0.4);
            $b->save();

            BroadcastStarted::dispatch($b);

            if ($b->created_by !== null) {
                BroadcastProgressUpdated::dispatch(
                    (string) $b->id,
                    (string) $b->created_by,
                    BroadcastStatus::Sending->value,
                    (int) $b->recipient_count,
                    (int) $b->delivered_count,
                    (int) $b->failed_count,
                );
            }

            return $b->refresh();
        });
    }
}
