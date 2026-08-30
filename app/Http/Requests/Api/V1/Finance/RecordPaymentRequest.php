<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Payment;
use Illuminate\Validation\Rules\Enum;

final class RecordPaymentRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'gateway_fee_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'received_at' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
