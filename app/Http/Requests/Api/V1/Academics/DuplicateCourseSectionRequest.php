<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class DuplicateCourseSectionRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('course_section')) ?? false;
    }

    public function rules(): array
    {
        return [
            'section_label' => ['required', 'string', 'max:64'],
        ];
    }
}
