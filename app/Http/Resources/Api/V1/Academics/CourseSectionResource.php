<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academics;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\CourseSection;
use Illuminate\Http\Request;

/**
 * @mixin CourseSection
 */
final class CourseSectionResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_label' => $this->academicYear?->label ?? '',
            'campus_id' => $this->campus_id,
            'campus_name' => $this->campus?->name ?? '',
            'subject_id' => $this->subject_id,
            'subject_code' => $this->subject?->code ?? '',
            'subject_name' => $this->subject?->name ?? '',
            'grade_label' => $this->grade_label,
            'section_label' => $this->section_label,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->teacher?->full_name ?? '',
            'room' => $this->room,
            'enrolled_count' => (int) ($this->students_count ?? $this->students()->count()),
            'capacity' => (int) $this->capacity,
            'status' => $this->status->value,
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
