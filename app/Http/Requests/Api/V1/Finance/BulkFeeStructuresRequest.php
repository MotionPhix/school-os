<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\FeeStructure;
use Illuminate\Validation\Rule;

final class BulkFeeStructuresRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FeeStructure::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('finance_fee_structures', 'id')->where('tenant_id', $tenantId)],
            'action' => ['required', 'string', Rule::in(['activate', 'deactivate', 'delete'])],
        ];
    }
}
