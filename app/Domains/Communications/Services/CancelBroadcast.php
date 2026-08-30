<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\BroadcastCancelled;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelBroadcast
{
    public function handle(Broadcast $b): Broadcast
    {
        if (in_array($b->status, [BroadcastStatus::Completed, BroadcastStatus::Failed], true)) {
            throw ValidationException::withMessages([
                'status' => 'Broadcast has already finished.',
            ]);
        }

        return DB::transaction(function () use ($b) {
            $b->status = BroadcastStatus::Failed;
            $b->completed_at = now();
            $b->save();

            BroadcastCancelled::dispatch($b);

            return $b->refresh();
        });
    }
}
