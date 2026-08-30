<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Attendance;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\AttendanceSession;
use Illuminate\Validation\Rule;

final class BulkAttendanceSessionsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceSession::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('attendance_sessions', 'id')->where('tenant_id', $tenantId)],
            'action' => ['required', 'string', Rule::in(['submit', 'reopen', 'delete'])],
        ];
    }
}
