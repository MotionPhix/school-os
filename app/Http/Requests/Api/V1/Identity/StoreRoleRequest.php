<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Enums\RoleScope;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreRoleRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9\._-]+$/',
                Rule::unique('roles', 'key')->where('tenant_id', $this->tenantId()),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:500'],
            'scope' => ['required', new Enum(RoleScope::class)],
            'permission_keys' => ['required', 'array'],
            'permission_keys.*' => ['string', 'max:120'],
        ];
    }
}
