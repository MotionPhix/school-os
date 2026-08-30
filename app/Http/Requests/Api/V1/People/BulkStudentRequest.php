<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\StudentStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class BulkStudentRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['set_status', 'transfer_campus'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('students', 'id')->where('tenant_id', $this->tenantId())],
            'status' => ['required_if:action,set_status', new Enum(StudentStatus::class)],
            'campus_id' => [
                'required_if:action,transfer_campus',
                'uuid',
                Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId()),
            ],
        ];
    }
}
