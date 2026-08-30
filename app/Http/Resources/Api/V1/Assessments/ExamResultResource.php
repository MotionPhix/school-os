<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Assessments;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\ExamResult;
use Illuminate\Http\Request;

/**
 * @mixin ExamResult
 */
final class ExamResultResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'student_id' => $this->student_id,
            'student_name' => $this->student?->full_name ?? '',
            'student_initials' => $this->student?->avatar_initials ?? '',
            'score' => $this->score,
            'band' => $this->band?->value,
            'remarks' => $this->remarks,
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
