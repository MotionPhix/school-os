<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\AttendanceMark;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class SetAttendanceMarkRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceMark::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'status' => ['required', new Enum(AttendanceStatus::class)],
            'minutes_late' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:240'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
