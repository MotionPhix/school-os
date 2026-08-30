<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\GradebookEntry;

final class BulkGradebookRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', GradebookEntry::class) ?? false;
    }

    public function rules(): array
    {
        $caMax = (int) config('academics.gradebook.continuous_assessment_max', 40);
        $examMax = (int) config('academics.gradebook.exam_max', 60);

        return [
            'entries' => ['required', 'array', 'min:1', 'max:300'],
            'entries.*.id' => ['required', 'uuid', 'exists:gradebook_entries,id'],
            'entries.*.continuous_assessment' => ['required', 'integer', 'min:0', "max:{$caMax}"],
            'entries.*.exam_score' => ['required', 'integer', 'min:0', "max:{$examMax}"],
            'entries.*.remarks' => ['sometimes', 'nullable', 'string', 'max:280'],
        ];
    }
}
