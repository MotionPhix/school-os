<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\CampusStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Campus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class BulkCampusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Campus::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['set_status', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId())],
            'status' => ['required_if:action,set_status', new Enum(CampusStatus::class)],
        ];
    }
}
