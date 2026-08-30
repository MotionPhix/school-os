<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Attendance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\AttendanceSession;
use Illuminate\Http\Request;

/**
 * @mixin AttendanceSession
 */
final class AttendanceSessionResource extends CapabilityResource
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
            'section_label' => mb_trim(($section?->grade_label ?? '').' — '.($section?->section_label ?? ''), ' —'),
            'teacher_name' => $section?->teacher?->full_name ?? '',
            'date' => $this->date?->toDateString(),
            'period' => (int) $this->period,
            'status' => $this->status->value,
            'present_count' => (int) $this->present_count,
            'absent_count' => (int) $this->absent_count,
            'late_count' => (int) $this->late_count,
            'excused_count' => (int) $this->excused_count,
            'total_count' => (int) $this->total_count,
            'taken_at' => $this->iso($this->taken_at),
            'updated_at' => $this->iso($this->updated_at),
            'marks' => $this->whenLoaded('marks', fn () => AttendanceMarkResource::collection($this->marks)->resolve()),
        ];
    }
}
