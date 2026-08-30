<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\StaffCategory;
use App\Enums\StaffEmploymentType;
use App\Enums\StaffStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\StaffMember;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreStaffMemberRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StaffMember::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => [
                'required', 'uuid',
                Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'user_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id'),
            ],
            'staff_number' => [
                'required', 'string', 'max:32',
                Rule::unique('staff_members', 'staff_number')->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'full_name' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', new Enum(StaffCategory::class)],
            'department' => ['required', 'string', 'max:120'],
            'employment_type' => ['required', new Enum(StaffEmploymentType::class)],
            'status' => ['sometimes', new Enum(StaffStatus::class)],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_address_line' => ['nullable', 'string', 'max:200'],
            'contact_city' => ['nullable', 'string', 'max:120'],
            'contact_region' => ['nullable', 'string', 'max:120'],
            'subjects_taught' => ['sometimes', 'array'],
            'subjects_taught.*' => ['string', 'max:80'],
            'hired_on' => ['required', 'date'],
        ];
    }
}
