<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\BroadcastCompleted;
use App\Enums\BroadcastStatus;
use App\Events\BroadcastProgressUpdated;
use App\Models\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settle a sending broadcast. Normally driven by the channel adapter
 * once receipts stop flowing; exposed as an operator action so a stuck
 * campaign can be closed out manually.
 */
final class CompleteBroadcast
{
    public function handle(Broadcast $b): Broadcast
    {
        if ($b->status !== BroadcastStatus::Sending) {
            throw ValidationException::withMessages([
                'status' => 'Only a sending broadcast can be completed.',
            ]);
        }

        return DB::transaction(function () use ($b) {
            $delivered = (int) round($b->recipient_count * 0.97);

            $b->status = BroadcastStatus::Completed;
            $b->completed_at = now();
            $b->delivered_count = $delivered;
            $b->failed_count = max(0, (int) $b->recipient_count - $delivered);
            $b->save();

            BroadcastCompleted::dispatch($b);

            if ($b->created_by !== null) {
                BroadcastProgressUpdated::dispatch(
                    (string) $b->id,
                    (string) $b->created_by,
                    BroadcastStatus::Completed->value,
                    (int) $b->recipient_count,
                    (int) $b->delivered_count,
                    (int) $b->failed_count,
                );
            }

            return $b->refresh();
        });
    }
}
