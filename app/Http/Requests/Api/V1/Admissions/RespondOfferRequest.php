<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Enums\OfferStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class RespondOfferRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respondOffer', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', Rule::in([OfferStatus::Accepted->value, OfferStatus::Declined->value])],
        ];
    }
}
