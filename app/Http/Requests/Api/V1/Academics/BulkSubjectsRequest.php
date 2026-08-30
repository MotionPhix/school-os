<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Subject;

final class BulkSubjectsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Subject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:set_category,set_core,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'uuid', 'exists:subjects,id'],
            'category' => ['required_if:action,set_category', 'string', 'max:64'],
            'is_core' => ['required_if:action,set_core', 'boolean'],
        ];
    }
}
