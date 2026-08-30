<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\AcademicYear;
use Illuminate\Validation\Rules\Enum;

final class TransitionAcademicYearRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('academic_year');

        return $year instanceof AcademicYear
            && ($this->user()?->can('update', $year) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(AcademicYearStatus::class)],
        ];
    }
}
