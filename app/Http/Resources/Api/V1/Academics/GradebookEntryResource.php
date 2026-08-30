<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academics;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\GradebookEntry;
use Illuminate\Http\Request;

/**
 * @mixin GradebookEntry
 */
final class GradebookEntryResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'course_section_id' => $this->course_section_id,
            'term_id' => $this->term_id,
            'term_name' => $this->term?->name ?? '',
            'student_id' => $this->student_id,
            'student_name' => $this->student?->full_name ?? '',
            'student_initials' => $this->student?->avatar_initials ?? '',
            'continuous_assessment' => (int) $this->continuous_assessment,
            'exam_score' => (int) $this->exam_score,
            'total' => (int) $this->total,
            'band' => $this->band->value,
            'remarks' => $this->remarks,
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
