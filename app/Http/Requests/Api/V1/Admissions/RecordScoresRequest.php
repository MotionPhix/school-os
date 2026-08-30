<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admissions;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class RecordScoresRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'assessment_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'interview_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Only the keys actually present are forwarded; an absent key leaves the
     * stored score untouched, an explicit null clears it.
     *
     * @return array{assessment_score?:int|null, interview_score?:int|null}
     */
    public function scores(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(['assessment_score', 'interview_score']),
        );
    }
}
