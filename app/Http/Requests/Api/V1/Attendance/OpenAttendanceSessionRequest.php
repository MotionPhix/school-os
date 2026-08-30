<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Attendance;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\AttendanceSession;
use Illuminate\Validation\Rule;

final class OpenAttendanceSessionRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceSession::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'course_section_id' => ['required', 'uuid', Rule::exists('course_sections', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date_format:Y-m-d'],
            'period' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
