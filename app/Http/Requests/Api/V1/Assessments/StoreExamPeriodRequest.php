<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Enums\ExamPeriodStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\ExamPeriod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreExamPeriodRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExamPeriod::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'term_id' => ['required', 'uuid', Rule::exists('terms', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'status' => ['sometimes', new Enum(ExamPeriodStatus::class)],
        ];
    }
}
