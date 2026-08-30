<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Enums\ExamStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Exam;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreExamRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Exam::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'period_id' => ['required', 'uuid', Rule::exists('exam_periods', 'id')->where('tenant_id', $tenantId)],
            'course_section_id' => ['required', 'uuid', Rule::exists('course_sections', 'id')->where('tenant_id', $tenantId)],
            'paper_title' => ['required', 'string', 'max:160'],
            'scheduled_on' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'room' => ['nullable', 'string', 'max:64'],
            'max_score' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'pass_mark' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'status' => ['sometimes', new Enum(ExamStatus::class)],
        ];
    }
}
