<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\BroadcastCompleted;
use App\Domains\Communications\Support\BroadcastFailureReasonWeights;
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
            $failed = max(0, (int) $b->recipient_count - $delivered);

            $b->status = BroadcastStatus::Completed;
            $b->completed_at = now();
            $b->delivered_count = $delivered;
            $b->failed_count = $failed;

            // Seed the failure taxonomy (integer distribution) and arm the
            // first retry after the base backoff interval.
            $weights = config('observability.broadcast_delivery_retry.failure_weights');
            $b->failure_reasons = BroadcastFailureReasonWeights::distribute(
                $failed,
                is_array($weights) ? $weights : [],
            );
            if ($failed > 0) {
                $baseMinutes = config('observability.broadcast_delivery_retry.base_interval_minutes');
                $b->delivery_next_retry_at = now()->addMinutes(
                    is_int($baseMinutes) ? max(1, $baseMinutes) : 15,
                );
            }

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
