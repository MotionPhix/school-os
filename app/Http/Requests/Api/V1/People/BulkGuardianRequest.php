<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\GuardianStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Guardian;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class BulkGuardianRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Guardian::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['set_portal_status', 'resend_invite'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('guardians', 'id')->where('tenant_id', $this->tenantId())],
            'status' => ['required_if:action,set_portal_status', new Enum(GuardianStatus::class)],
        ];
    }
}
