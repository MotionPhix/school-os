<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\GradebookEntry;

final class CurveGradebookRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', GradebookEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'course_section_id' => ['required', 'uuid', 'exists:course_sections,id'],
            'term_id' => ['sometimes', 'nullable', 'uuid', 'exists:terms,id'],
            'points' => ['required', 'integer', 'min:-20', 'max:20'],
        ];
    }
}
