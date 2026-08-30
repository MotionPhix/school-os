<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\CampusStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Campus;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCampusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Campus::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('campuses', 'code')->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', new Enum(CampusStatus::class)],
            'address_line' => ['required', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'max:64'],
            'building_count' => ['sometimes', 'integer', 'min:0'],
            'opened_at' => ['nullable', 'date'],
        ];
    }
}
