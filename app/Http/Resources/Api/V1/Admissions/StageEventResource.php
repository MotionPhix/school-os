<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admissions;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\ApplicationStageEvent;
use Illuminate\Http\Request;

/**
 * @mixin ApplicationStageEvent
 */
final class StageEventResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_stage' => $this->from_stage?->value,
            'to_stage' => $this->to_stage->value,
            'note' => $this->note,
            'actor_name' => $this->actor_name,
            'occurred_at' => $this->iso($this->occurred_at),
        ];
    }
}
