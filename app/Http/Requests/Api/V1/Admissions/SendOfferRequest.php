<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class SendOfferRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sendOffer', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'fee_amount' => ['required', 'integer', 'min:0'], // minor units
            'currency_code' => ['required', 'string', 'size:3'],
            'expires_on' => ['nullable', 'date', 'after:today'],
        ];
    }
}
