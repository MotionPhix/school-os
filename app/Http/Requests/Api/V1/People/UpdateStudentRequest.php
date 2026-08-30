<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\Gender;
use App\Enums\StudentStage;
use App\Enums\StudentStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateStudentRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;

        return [
            'campus_id' => [
                'sometimes', 'uuid',
                Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'admission_number' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('students', 'admission_number')
                    ->ignore($studentId)
                    ->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'full_name' => ['sometimes', 'string', 'max:160'],
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['sometimes', new Enum(Gender::class)],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'stage' => ['sometimes', new Enum(StudentStage::class)],
            'grade_label' => ['sometimes', 'string', 'max:32'],
            'house' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', new Enum(StudentStatus::class)],
            'enrolled_on' => ['nullable', 'date'],
        ];
    }
}
