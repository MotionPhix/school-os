<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Enums\ExamStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Exam;
use Illuminate\Validation\Rule;

final class BulkExamsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Exam::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $actions = array_merge(array_column(ExamStatus::cases(), 'value'), ['delete']);

        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('exams', 'id')->where('tenant_id', $tenantId)],
            'action' => ['required', 'string', Rule::in($actions)],
        ];
    }
}
