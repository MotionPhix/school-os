<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\Gender;
use App\Enums\StudentStage;
use App\Enums\StudentStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Student;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreStudentRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => [
                'required', 'uuid',
                Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'admission_number' => [
                'required', 'string', 'max:32',
                Rule::unique('students', 'admission_number')->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'full_name' => ['required', 'string', 'max:160'],
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', new Enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'stage' => ['required', new Enum(StudentStage::class)],
            'grade_label' => ['required', 'string', 'max:32'],
            'house' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', new Enum(StudentStatus::class)],
            'enrolled_on' => ['nullable', 'date'],
        ];
    }
}
