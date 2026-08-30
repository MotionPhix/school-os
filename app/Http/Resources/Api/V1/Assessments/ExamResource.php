<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Assessments;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Exam;
use Illuminate\Http\Request;

/**
 * @mixin Exam
 */
final class ExamResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        $section = $this->courseSection;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'period_id' => $this->period_id,
            'period_name' => $this->period?->name ?? '',
            'course_section_id' => $this->course_section_id,
            'subject_code' => $section?->subject?->code ?? '',
            'subject_name' => $section?->subject?->name ?? '',
            'grade_label' => $section?->grade_label ?? '',
            'section_label' => $section?->section_label ?? '',
            'teacher_name' => $section?->teacher?->full_name ?? '',
            'paper_title' => $this->paper_title,
            'scheduled_on' => $this->scheduled_on?->toDateString(),
            'starts_at' => $this->starts_at,
            'duration_minutes' => (int) $this->duration_minutes,
            'room' => $this->room,
            'max_score' => (int) $this->max_score,
            'pass_mark' => (int) $this->pass_mark,
            'status' => $this->status->value,
            'result_count' => (int) ($this->graded_count ?? $this->results()->graded()->count()),
            'updated_at' => $this->iso($this->updated_at),
            'results' => $this->whenLoaded('results', fn () => ExamResultResource::collection($this->results)->resolve()),
        ];
    }
}
