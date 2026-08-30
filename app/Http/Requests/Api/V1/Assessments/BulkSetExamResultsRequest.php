<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class BulkSetExamResultsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('exam')) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $max = (int) ($this->route('exam')?->max_score ?? 100);

        return [
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'entries.*.score' => ['present', 'nullable', 'integer', 'min:0', "max:{$max}"],
            'entries.*.remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
