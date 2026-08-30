<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\StaffStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class SetStaffStatusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('staff_member')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(StaffStatus::class)],
        ];
    }
}
