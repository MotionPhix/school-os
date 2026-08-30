<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Enums\TenantStatus;
use App\Enums\TenantTier;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateTenantRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tenant')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'legal_name' => ['sometimes', 'string', 'max:200'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'tier' => ['sometimes', new Enum(TenantTier::class)],
            'status' => ['sometimes', new Enum(TenantStatus::class)],
        ];
    }
}
