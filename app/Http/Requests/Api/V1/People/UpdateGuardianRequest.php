<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\GuardianStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateGuardianRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('guardian')) ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:160'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'employer' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_address_line' => ['nullable', 'string', 'max:200'],
            'contact_city' => ['nullable', 'string', 'max:120'],
            'contact_region' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['sometimes', 'string', 'max:16'],
            'portal_status' => ['sometimes', new Enum(GuardianStatus::class)],
        ];
    }
}
