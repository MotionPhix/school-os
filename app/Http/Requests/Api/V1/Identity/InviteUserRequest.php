<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Invitation;
use Illuminate\Validation\Rule;

final class InviteUserRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:200'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'uuid',
                Rule::exists('roles', 'id')->where(function ($q): void {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $this->tenantId());
                }),
            ],
        ];
    }
}
