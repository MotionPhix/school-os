<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academics;

use App\Enums\Weekday;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\TimetableSlot;
use Illuminate\Validation\Rules\Enum;

final class MoveTimetableSlotRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('timetable_slot') ?? TimetableSlot::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'weekday' => ['required', new Enum(Weekday::class)],
            'period' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
