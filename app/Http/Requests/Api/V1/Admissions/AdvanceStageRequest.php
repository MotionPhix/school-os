<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Enums\PipelineStage;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class AdvanceStageRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'to_stage' => ['required', new Enum(PipelineStage::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
