<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\TermStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Term;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreTermRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Term::class) ?? false;
    }

    public function rules(): array
    {
        $yearId = $this->route('academic_year')?->id;

        return [
            'name' => ['required', 'string', 'max:64'],
            'sequence' => [
                'required', 'integer', 'min:1', 'max:12',
                Rule::unique('terms', 'sequence')->where('academic_year_id', $yearId)->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'instructional_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'status' => ['sometimes', new Enum(TermStatus::class)],
        ];
    }
}
