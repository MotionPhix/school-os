<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Explicit pivot for the many-to-many `Student <-> Guardian` link.
 *
 * Carries the `relationship` (Mother, Father, Guardian, Sponsor, …) and
 * a `is_primary` flag — the guardian receiving primary comms per student.
 * `tenant_id` is duplicated for query performance and index alignment
 * with the two aggregate tables.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $student_id
 * @property string $guardian_id
 * @property string $relationship
 * @property bool $is_primary
 */
#[Fillable([
    'tenant_id',
    'student_id',
    'guardian_id',
    'relationship',
    'is_primary',
])]
final class StudentGuardian extends Pivot
{
    use HasUuid;

    public $incrementing = false;

    protected $table = 'student_guardians';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
