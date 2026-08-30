<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\CampusStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateCampusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('campus')) ?? false;
    }

    public function rules(): array
    {
        $campusId = $this->route('campus')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => [
                'sometimes', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('campuses', 'code')
                    ->ignore($campusId)
                    ->where('tenant_id', $this->tenantId())->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', new Enum(CampusStatus::class)],
            'address_line' => ['sometimes', 'string', 'max:200'],
            'city' => ['sometimes', 'string', 'max:120'],
            'region' => ['sometimes', 'string', 'max:120'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'building_count' => ['sometimes', 'integer', 'min:0'],
            'opened_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
