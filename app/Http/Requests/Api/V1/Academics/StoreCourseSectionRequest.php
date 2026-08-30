<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Enums\CourseStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\CourseSection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCourseSectionRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CourseSection::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')->where('tenant_id', $this->tenantId())],
            'campus_id' => ['required', 'uuid', Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId())],
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')->where('tenant_id', $this->tenantId())],
            'teacher_id' => ['required', 'uuid', Rule::exists('staff_members', 'id')->where('tenant_id', $this->tenantId())],
            'grade_label' => ['required', 'string', 'max:32'],
            'section_label' => ['required', 'string', 'max:64'],
            'room' => ['sometimes', 'nullable', 'string', 'max:64'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'status' => ['sometimes', new Enum(CourseStatus::class)],
        ];
    }
}
