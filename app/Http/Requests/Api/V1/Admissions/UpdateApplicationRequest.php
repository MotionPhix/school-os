<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Enums\ApplicationSource;
use App\Enums\Gender;
use App\Enums\StudentStage;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateApplicationRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'applicant_full_name' => ['sometimes', 'string', 'max:160'],
            'applicant_preferred_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', new Enum(Gender::class)],
            'guardian_name' => ['sometimes', 'string', 'max:160'],
            'guardian_email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'guardian_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('guardians', 'id')->where('tenant_id', $this->tenantId())],
            'campus_id' => ['sometimes', 'uuid', Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId())],
            'academic_year_id' => ['sometimes', 'uuid', Rule::exists('academic_years', 'id')->where('tenant_id', $this->tenantId())],
            'intended_stage' => ['sometimes', new Enum(StudentStage::class)],
            'intended_grade_label' => ['sometimes', 'string', 'max:32'],
            'source' => ['sometimes', new Enum(ApplicationSource::class)],
            'assessment_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'interview_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
