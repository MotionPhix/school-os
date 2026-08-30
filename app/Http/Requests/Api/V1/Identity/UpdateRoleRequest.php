<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class UpdateRoleRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('role')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'string', 'max:500'],
            'permission_keys' => ['sometimes', 'array'],
            'permission_keys.*' => ['string', 'max:120'],
        ];
    }
}
