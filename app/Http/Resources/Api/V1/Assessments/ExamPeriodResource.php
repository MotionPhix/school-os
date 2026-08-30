<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Assessments;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\ExamPeriod;
use Illuminate\Http\Request;

/**
 * @mixin ExamPeriod
 */
final class ExamPeriodResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_label' => $this->academicYear?->label ?? '',
            'term_id' => $this->term_id,
            'term_name' => $this->term?->name ?? '',
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status->value,
            'exam_count' => (int) ($this->exams_count ?? $this->exams()->count()),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
