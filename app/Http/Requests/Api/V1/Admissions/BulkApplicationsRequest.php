<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Enums\PipelineStage;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Application;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class BulkApplicationsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Application::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['advance_stage', 'reject', 'withdraw'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['string', 'uuid'],
            'to_stage' => ['required_if:action,advance_stage', new Enum(PipelineStage::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
