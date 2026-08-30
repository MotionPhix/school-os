<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\GradebookEntry;
use Illuminate\Validation\Rule;

final class UpsertGradebookEntryRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GradebookEntry::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'course_section_id' => ['required', 'uuid', Rule::exists('course_sections', 'id')->where('tenant_id', $tenantId)],
            'term_id' => ['required', 'uuid', Rule::exists('terms', 'id')->where('tenant_id', $tenantId)],
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'continuous_assessment' => ['required', 'integer', 'min:0'],
            'exam_score' => ['required', 'integer', 'min:0'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
