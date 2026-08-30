<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\AcademicYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreAcademicYearRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AcademicYear::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => [
                'required', 'string', 'max:32',
                Rule::unique('academic_years', 'label')->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['sometimes', new Enum(AcademicYearStatus::class)],
        ];
    }
}
