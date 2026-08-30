<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Enums\StudentStage;
use App\Enums\SubjectCategory;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreSubjectRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Subject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:16',
                Rule::unique('subjects', 'code')->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', new Enum(SubjectCategory::class)],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => [new Enum(StudentStage::class)],
            'is_core' => ['sometimes', 'boolean'],
            'credit_hours' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
