<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Attendance;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\AttendanceMark;
use Illuminate\Http\Request;

/**
 * @mixin AttendanceMark
 */
final class AttendanceMarkResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'student_id' => $this->student_id,
            'student_name' => $this->student?->full_name ?? '',
            'student_initials' => $this->student?->avatar_initials ?? '',
            'status' => $this->status->value,
            'minutes_late' => $this->minutes_late,
            'note' => $this->note,
        ];
    }
}
