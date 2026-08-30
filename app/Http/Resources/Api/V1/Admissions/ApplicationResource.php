<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admissions;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Application;
use Illuminate\Http\Request;

/**
 * @mixin Application
 */
final class ApplicationResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'reference' => $this->reference,
            'applicant_full_name' => $this->applicant_full_name,
            'applicant_preferred_name' => $this->applicant_preferred_name,
            'avatar_initials' => $this->avatar_initials,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'gender' => $this->gender->value,
            'guardian_name' => $this->guardian_name,
            'guardian_email' => $this->guardian_email,
            'guardian_phone' => $this->guardian_phone,
            'campus_id' => $this->campus_id,
            'campus_name' => $this->campus?->name ?? '',
            'academic_year_id' => $this->academic_year_id,
            'academic_year_label' => $this->academicYear?->label ?? '',
            'intended_stage' => $this->intended_stage->value,
            'intended_grade_label' => $this->intended_grade_label,
            'source' => $this->source->value,
            'stage' => $this->stage->value,
            'assessment_score' => $this->assessment_score,
            'interview_score' => $this->interview_score,
            'offer' => $this->currentOffer
                ? (new OfferSummaryResource($this->currentOffer))->toArray($request)
                : null,
            'student_id' => $this->student_id,
            'submitted_at' => $this->iso($this->submitted_at),
            'updated_at' => $this->iso($this->updated_at),
            'timeline' => StageEventResource::collection($this->whenLoaded('timeline'))->resolve($request),
        ];
    }
}
