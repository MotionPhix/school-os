<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Enums\ApplicationSource;
use App\Enums\Gender;
use App\Enums\PipelineStage;
use App\Enums\StudentStage;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Application;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreApplicationRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Application::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'applicant_full_name' => ['required', 'string', 'max:160'],
            'applicant_preferred_name' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', new Enum(Gender::class)],
            'guardian_name' => ['required', 'string', 'max:160'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'guardian_id' => ['nullable', 'uuid', Rule::exists('guardians', 'id')->where('tenant_id', $this->tenantId())],
            'campus_id' => ['required', 'uuid', Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId())],
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')->where('tenant_id', $this->tenantId())],
            'intended_stage' => ['required', new Enum(StudentStage::class)],
            'intended_grade_label' => ['required', 'string', 'max:32'],
            'source' => ['required', new Enum(ApplicationSource::class)],
            'stage' => ['sometimes', new Enum(PipelineStage::class)],
            'source_note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
