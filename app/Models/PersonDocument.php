<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonSubject;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Profile document attached to a person (student, guardian, staff).
 *
 * Uses a string discriminator (`students`|`guardians`|`staff`) rather
 * than a fully-qualified class name in `subject_type` so the URL segment
 * and the DB value stay identical — controllers map via PersonSubject.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property string $storage_path
 * @property string|null $uploaded_by
 * @property Carbon $uploaded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'subject_type',
    'subject_id',
    'name',
    'mime',
    'size',
    'storage_path',
    'uploaded_by',
    'uploaded_at',
])]
final class PersonDocument extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function subjectEnum(): PersonSubject
    {
        return PersonSubject::from($this->subject_type);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
