<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\TermStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Term;
use Illuminate\Validation\Rules\Enum;

final class TransitionTermRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        $term = $this->route('term');

        return $term instanceof Term
            && ($this->user()?->can('update', $term) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TermStatus::class)],
        ];
    }
}
