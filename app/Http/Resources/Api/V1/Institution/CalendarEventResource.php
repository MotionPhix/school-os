<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Institution;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;

/**
 * @mixin CalendarEvent
 */
final class CalendarEventResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'campus_id' => $this->campus_id,
            'academic_year_id' => $this->academic_year_id,
            'title' => $this->title,
            'kind' => $this->kind->value,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'all_day' => (bool) $this->all_day,
            'audience' => $this->audience->value,
            'description' => $this->description,
        ];
    }
}
