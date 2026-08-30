<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Enums\Weekday;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class ScheduleTimetableSlotRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedule', $this->route('course_section')) ?? false;
    }

    public function rules(): array
    {
        return [
            'weekday' => ['required', new Enum(Weekday::class)],
            'period' => ['required', 'integer', 'min:1', 'max:12'],
            'starts_at' => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'ends_at' => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'room' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
