<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Institution;

use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\InstitutionProfile;
use App\Models\StaffMember;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * @mixin InstitutionProfile
 */
final class InstitutionProfileResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'motto' => $this->motto,
            'logo_url' => $this->logo_url,
            'established_year' => (int) $this->established_year,
            'type' => $this->type->value,
            'accreditation_status' => $this->accreditation_status->value,
            'accreditation_body' => $this->accreditation_body,
            'accreditation_number' => $this->accreditation_number,
            'accredited_until' => $this->accredited_until?->toDateString(),
            'student_capacity' => (int) $this->student_capacity,
            'active_student_count' => Student::query()->withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->where('status', StudentStatus::Enrolled->value)
                ->count(),
            'staff_count' => StaffMember::query()->withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->where('status', StaffStatus::Active->value)
                ->count(),
            'languages_of_instruction' => $this->languages_of_instruction,
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'website' => $this->contact_website,
                'address_line' => $this->address_line,
                'city' => $this->city,
                'region' => $this->region,
                'postal_code' => $this->postal_code,
            ],
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
