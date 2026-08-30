<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\CalendarAudience;
use App\Enums\CalendarEventKind;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\CalendarEvent;
use Illuminate\Validation\Rules\Enum;

final class StoreCalendarEventRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => [
                'required', 'uuid',
                'exists:academic_years,id,tenant_id,'.$this->tenantId(),
            ],
            'campus_id' => [
                'nullable', 'uuid',
                'exists:campuses,id,tenant_id,'.$this->tenantId(),
            ],
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', new Enum(CalendarEventKind::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'all_day' => ['sometimes', 'boolean'],
            'audience' => ['sometimes', new Enum(CalendarAudience::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
