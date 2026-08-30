<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\BillingCycle;
use App\Enums\CurrencyCode;
use App\Enums\FeeCategory;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\FeeStructure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreFeeStructureRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FeeStructure::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'academic_year_id' => ['nullable', 'uuid', Rule::exists('academic_years', 'id')->where('tenant_id', $tenantId)],
            'academic_year_label' => ['required', 'string', 'max:40'],
            'grade_label' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', new Enum(FeeCategory::class)],
            'cycle' => ['required', new Enum(BillingCycle::class)],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', new Enum(CurrencyCode::class)],
            'is_active' => ['sometimes', 'boolean'],
            'applies_to_student_count' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
