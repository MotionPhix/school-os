<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\CurrencyCode;
use App\Enums\FeeCategory;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Invoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreInvoiceRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'term_id' => ['nullable', 'uuid', Rule::exists('terms', 'id')->where('tenant_id', $tenantId)],
            'issued_on' => ['sometimes', 'date_format:Y-m-d'],
            'due_on' => ['sometimes', 'date_format:Y-m-d'],
            'currency' => ['sometimes', new Enum(CurrencyCode::class)],
            'discount_minor' => ['sometimes', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.fee_structure_id' => ['nullable', 'uuid', Rule::exists('finance_fee_structures', 'id')->where('tenant_id', $tenantId)],
            'lines.*.description' => ['required', 'string', 'max:240'],
            'lines.*.category' => ['required', new Enum(FeeCategory::class)],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_amount_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
