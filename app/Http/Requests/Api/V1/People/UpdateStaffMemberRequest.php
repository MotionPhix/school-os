<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\StaffCategory;
use App\Enums\StaffEmploymentType;
use App\Enums\StaffStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateStaffMemberRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('staff_member')) ?? false;
    }

    public function rules(): array
    {
        $staffId = $this->route('staff_member')?->id;

        return [
            'campus_id' => [
                'sometimes', 'uuid',
                Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'staff_number' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('staff_members', 'staff_number')
                    ->ignore($staffId)
                    ->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'full_name' => ['sometimes', 'string', 'max:160'],
            'title' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', new Enum(StaffCategory::class)],
            'department' => ['sometimes', 'string', 'max:120'],
            'employment_type' => ['sometimes', new Enum(StaffEmploymentType::class)],
            'status' => ['sometimes', new Enum(StaffStatus::class)],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_address_line' => ['nullable', 'string', 'max:200'],
            'contact_city' => ['nullable', 'string', 'max:120'],
            'contact_region' => ['nullable', 'string', 'max:120'],
            'subjects_taught' => ['sometimes', 'array'],
            'subjects_taught.*' => ['string', 'max:80'],
            'hired_on' => ['sometimes', 'date'],
        ];
    }
}
