<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Enums\ExamPeriodStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\ExamPeriod;
use Illuminate\Validation\Rule;

final class BulkExamPeriodsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExamPeriod::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $actions = array_merge(array_column(ExamPeriodStatus::cases(), 'value'), ['delete']);

        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['uuid', Rule::exists('exam_periods', 'id')->where('tenant_id', $tenantId)],
            'action' => ['required', 'string', Rule::in($actions)],
        ];
    }
}
