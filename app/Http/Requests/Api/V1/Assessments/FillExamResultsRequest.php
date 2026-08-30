<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class FillExamResultsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('exam')) ?? false;
    }

    public function rules(): array
    {
        $max = (int) ($this->route('exam')?->max_score ?? 100);

        return [
            'scope' => ['required', 'string', Rule::in(['all', 'remaining'])],
            'score' => ['required', 'integer', 'min:0', "max:{$max}"],
        ];
    }
}
