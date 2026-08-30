<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Enums\AccreditationStatus;
use App\Enums\InstitutionType;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\InstitutionProfile;
use Illuminate\Validation\Rules\Enum;

final class UpsertInstitutionProfileRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', InstitutionProfile::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['required', 'string', 'max:32'],
            'motto' => ['nullable', 'string', 'max:200'],
            'established_year' => ['required', 'integer', 'between:1800,'.(int) date('Y')],
            'type' => ['required', new Enum(InstitutionType::class)],
            'accreditation_status' => ['required', new Enum(AccreditationStatus::class)],
            'accreditation_body' => ['nullable', 'string', 'max:160', 'required_if:accreditation_status,accredited'],
            'accreditation_number' => ['nullable', 'string', 'max:120'],
            'accredited_until' => ['nullable', 'date_format:Y-m-d'],
            'student_capacity' => ['required', 'integer', 'min:0'],
            'languages_of_instruction' => ['required', 'array', 'min:1'],
            'languages_of_instruction.*' => ['string', 'max:32'],
            'contact_email' => ['required', 'email:rfc', 'max:160'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'contact_website' => ['nullable', 'url', 'max:200'],
            'address_line' => ['required', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
