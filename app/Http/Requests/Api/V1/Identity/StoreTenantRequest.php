<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Enums\TenantStatus;
use App\Enums\TenantTier;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreTenantRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9\-]+$/', Rule::unique('tenants', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['required', 'string', 'max:200'],
            'country_code' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency_code' => ['required', 'string', 'size:3'],
            'tier' => ['nullable', new Enum(TenantTier::class)],
            'status' => ['nullable', new Enum(TenantStatus::class)],
        ];
    }
}
