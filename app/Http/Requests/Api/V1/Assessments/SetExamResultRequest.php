<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\ExamResult;
use Illuminate\Validation\Rule;

final class SetExamResultRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExamResult::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
