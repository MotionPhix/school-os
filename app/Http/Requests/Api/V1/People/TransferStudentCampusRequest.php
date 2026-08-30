<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class TransferStudentCampusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => [
                'required',
                'uuid',
                Rule::exists('campuses', 'id')->where('tenant_id', $this->tenantId()),
            ],
        ];
    }
}
