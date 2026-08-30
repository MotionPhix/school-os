<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Enums\ExamPeriodStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class SetExamPeriodStatusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        $period = $this->route('exam_period');

        return $this->user()?->can('update', $period) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(ExamPeriodStatus::class)],
        ];
    }
}
