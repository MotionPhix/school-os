<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\BroadcastDrafted;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * Clone an existing campaign back to draft — the common way bursars
 * re-run a monthly reminder without retyping the template.
 * Delivery counters and timestamps are deliberately NOT copied.
 */
final class DuplicateBroadcast
{
    public function handle(Broadcast $source, ?User $actor = null): Broadcast
    {
        return DB::transaction(function () use ($source, $actor) {
            $copy = new Broadcast;
            $copy->fill([
                'tenant_id' => $source->tenant_id,
                'name' => $source->name.' (copy)',
                'channel' => $source->channel instanceof BackedEnum ? $source->channel->value : $source->channel,
                'audience' => $source->audience instanceof BackedEnum ? $source->audience->value : $source->audience,
                'audience_label' => $source->audience_label,
                'template_snippet' => $source->template_snippet,
                'status' => BroadcastStatus::Draft->value,
                'scheduled_for' => null,
                'recipient_count' => $source->recipient_count,
                'cost_minor' => $source->cost_minor,
                'currency' => $source->currency,
                'created_by' => $actor?->id,
            ]);
            $copy->save();

            BroadcastDrafted::dispatch($copy);

            return $copy->refresh();
        });
    }
}
