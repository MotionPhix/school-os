<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class RefundPaymentRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refund', $this->route('payment')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['sometimes', 'nullable', 'string', 'max:500']];
    }
}
