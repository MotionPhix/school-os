<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\StaffStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\StaffMember;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class BulkStaffMemberRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StaffMember::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['set_status', 'issue_login'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('staff_members', 'id')->where('tenant_id', $this->tenantId())],
            'status' => ['required_if:action,set_status', new Enum(StaffStatus::class)],
        ];
    }
}
