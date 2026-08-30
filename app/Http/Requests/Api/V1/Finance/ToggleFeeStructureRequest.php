<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class ToggleFeeStructureRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('fee_structure')) ?? false;
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
