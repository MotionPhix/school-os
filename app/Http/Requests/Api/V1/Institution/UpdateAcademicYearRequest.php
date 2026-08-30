<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateAcademicYearRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('academic_year')) ?? false;
    }

    public function rules(): array
    {
        $yearId = $this->route('academic_year')?->id;

        return [
            'label' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('academic_years', 'label')
                    ->ignore($yearId)
                    ->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date', 'after:starts_on'],
            'status' => ['sometimes', new Enum(AcademicYearStatus::class)],
        ];
    }
}
