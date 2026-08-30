<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class EnrollApplicationRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('enroll', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'admission_number' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
