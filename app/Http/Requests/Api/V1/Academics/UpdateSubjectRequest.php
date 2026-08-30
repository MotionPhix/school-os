<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Enums\StudentStage;
use App\Enums\SubjectCategory;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateSubjectRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('subject')) ?? false;
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'max:16',
                Rule::unique('subjects', 'code')
                    ->where('tenant_id', $this->tenantId())
                    ->ignore($subjectId)->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'name' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', new Enum(SubjectCategory::class)],
            'stages' => ['sometimes', 'array', 'min:1'],
            'stages.*' => [new Enum(StudentStage::class)],
            'is_core' => ['sometimes', 'boolean'],
            'credit_hours' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
