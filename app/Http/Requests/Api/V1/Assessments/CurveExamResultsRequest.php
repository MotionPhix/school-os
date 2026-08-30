<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class CurveExamResultsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('exam')) ?? false;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['points', 'percent'])],
            'amount' => ['required', 'numeric', 'between:-100,100'],
        ];
    }
}
