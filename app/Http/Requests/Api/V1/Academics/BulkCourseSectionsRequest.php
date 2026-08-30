<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\CourseSection;

final class BulkCourseSectionsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', CourseSection::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:publish,draft,archive,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'uuid', 'exists:course_sections,id'],
        ];
    }
}
