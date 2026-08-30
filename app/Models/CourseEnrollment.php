<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * CourseSection <-> Student pivot. Explicit model so we can attach
 * a tenant_id and a UUID primary key, mirroring StudentGuardian.
 */
#[Fillable([
    'tenant_id',
    'course_section_id',
    'student_id',
    'enrolled_at',
])]
final class CourseEnrollment extends Pivot
{
    use BelongsToTenant;
    use HasUuid;

    public $incrementing = false;

    protected $table = 'course_enrollments';

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
