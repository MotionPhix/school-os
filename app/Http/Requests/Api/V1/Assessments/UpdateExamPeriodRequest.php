<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class UpdateExamPeriodRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        $period = $this->route('exam_period');

        return $this->user()?->can('update', $period) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'starts_on' => ['sometimes', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }
}
