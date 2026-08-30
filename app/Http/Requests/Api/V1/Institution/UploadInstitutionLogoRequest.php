<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Institution;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\InstitutionProfile;

final class UploadInstitutionLogoRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', InstitutionProfile::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:512'],
        ];
    }
}
