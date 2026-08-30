<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Enums\StudentStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class SetStudentStatusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(StudentStatus::class)],
        ];
    }
}
