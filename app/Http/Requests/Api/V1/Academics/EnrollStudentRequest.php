<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class EnrollStudentRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('course_section')) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required', 'uuid',
                Rule::exists('students', 'id')->where('tenant_id', $this->tenantId()),
            ],
        ];
    }
}
