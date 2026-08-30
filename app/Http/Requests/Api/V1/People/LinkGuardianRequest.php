<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class LinkGuardianRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('update', $this->route('student')) ?? false)
            && ($this->user()?->can('update', $this->route('guardian')) ?? false);
    }

    public function rules(): array
    {
        return [
            'relationship' => ['required', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
