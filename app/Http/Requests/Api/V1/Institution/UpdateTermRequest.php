<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\TermStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateTermRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('term')) ?? false;
    }

    public function rules(): array
    {
        $yearId = $this->route('academic_year')?->id;
        $termId = $this->route('term')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:64'],
            'sequence' => [
                'sometimes', 'integer', 'min:1', 'max:12',
                Rule::unique('terms', 'sequence')
                    ->ignore($termId)
                    ->where('academic_year_id', $yearId)->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date', 'after:starts_on'],
            'instructional_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'status' => ['sometimes', new Enum(TermStatus::class)],
        ];
    }
}
