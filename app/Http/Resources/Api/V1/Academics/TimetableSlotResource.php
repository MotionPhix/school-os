<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academics;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;

/**
 * @mixin TimetableSlot
 */
final class TimetableSlotResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        $section = $this->courseSection;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'course_section_id' => $this->course_section_id,
            'subject_code' => $section?->subject?->code ?? '',
            'subject_name' => $section?->subject?->name ?? '',
            'grade_label' => $section?->grade_label ?? '',
            'teacher_name' => $section?->teacher?->full_name ?? '',
            'room' => $this->room ?? $section?->room,
            'weekday' => $this->weekday->value,
            'period' => (int) $this->period,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
        ];
    }
}
