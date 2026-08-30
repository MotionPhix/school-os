<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class UpdateExamRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $this->user()?->can('update', $exam) ?? false;
    }

    public function rules(): array
    {
        return [
            'paper_title' => ['sometimes', 'string', 'max:160'],
            'scheduled_on' => ['sometimes', 'date_format:Y-m-d'],
            'starts_at' => ['sometimes', 'date_format:H:i'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'room' => ['sometimes', 'nullable', 'string', 'max:64'],
            'max_score' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'pass_mark' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ];
    }
}
