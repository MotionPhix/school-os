<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\CalendarAudience;
use App\Enums\CalendarEventKind;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateCalendarEventRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('calendar_event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => [
                'sometimes', 'uuid',
                'exists:academic_years,id,tenant_id,'.$this->tenantId(),
            ],
            'campus_id' => [
                'sometimes', 'nullable', 'uuid',
                'exists:campuses,id,tenant_id,'.$this->tenantId(),
            ],
            'title' => ['sometimes', 'string', 'max:160'],
            'kind' => ['sometimes', new Enum(CalendarEventKind::class)],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date', 'after_or_equal:starts_on'],
            'all_day' => ['sometimes', 'boolean'],
            'audience' => ['sometimes', new Enum(CalendarAudience::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
