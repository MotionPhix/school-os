<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\FeeCategory;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateInvoiceRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('invoice')) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'issued_on' => ['sometimes', 'date_format:Y-m-d'],
            'due_on' => ['sometimes', 'date_format:Y-m-d'],
            'discount_minor' => ['sometimes', 'integer', 'min:0'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.fee_structure_id' => ['nullable', 'uuid', Rule::exists('finance_fee_structures', 'id')->where('tenant_id', $tenantId)],
            'lines.*.description' => ['required_with:lines', 'string', 'max:240'],
            'lines.*.category' => ['required_with:lines', new Enum(FeeCategory::class)],
            'lines.*.quantity' => ['required_with:lines', 'integer', 'min:1'],
            'lines.*.unit_amount_minor' => ['required_with:lines', 'integer', 'min:0'],
        ];
    }
}
