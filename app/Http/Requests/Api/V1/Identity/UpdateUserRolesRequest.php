<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRolesRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignRoles', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array'],
            'role_ids.*' => [
                'uuid',
                Rule::exists('roles', 'id')->where(function ($q): void {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $this->tenantId());
                }),
            ],
        ];
    }
}
