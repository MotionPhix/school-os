<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class VoidInvoiceRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('void', $this->route('invoice')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['sometimes', 'nullable', 'string', 'max:500']];
    }
}
