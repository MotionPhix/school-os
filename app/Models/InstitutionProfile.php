<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccreditationStatus;
use App\Enums\InstitutionType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Institution profile — one row per tenant.
 *
 * Represents the school's identity: name, motto, accreditation, capacity,
 * languages of instruction and primary contact. Kept as a first-class
 * model (rather than columns on `tenants`) so the Institution capability
 * owns its own persistence, contracts and audit trail — the tenant row
 * remains a lightweight security/billing anchor.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $short_name
 * @property string|null $motto
 * @property string|null $logo_url
 * @property int $established_year
 * @property InstitutionType $type
 * @property AccreditationStatus $accreditation_status
 * @property string|null $accreditation_body
 * @property string|null $accreditation_number
 * @property Carbon|null $accredited_until
 * @property int $student_capacity
 * @property array<int,string> $languages_of_instruction
 * @property string $contact_email
 * @property string $contact_phone
 * @property string|null $contact_website
 * @property string $address_line
 * @property string $city
 * @property string $region
 * @property string|null $postal_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'name',
    'short_name',
    'motto',
    'logo_url',
    'established_year',
    'type',
    'accreditation_status',
    'accreditation_body',
    'accreditation_number',
    'accredited_until',
    'student_capacity',
    'languages_of_instruction',
    'contact_email',
    'contact_phone',
    'contact_website',
    'address_line',
    'city',
    'region',
    'postal_code',
])]
final class InstitutionProfile extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected function casts(): array
    {
        return [
            'type' => InstitutionType::class,
            'accreditation_status' => AccreditationStatus::class,
            'accredited_until' => 'date',
            'languages_of_instruction' => 'array',
            'established_year' => 'integer',
            'student_capacity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
