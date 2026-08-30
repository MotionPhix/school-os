<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceMark — per-student status inside one AttendanceSession.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $session_id
 * @property string $student_id
 * @property AttendanceStatus $status
 * @property int|null $minutes_late
 * @property string|null $note
 * @property string|null $marked_by
 */
#[Fillable([
    'tenant_id',
    'session_id',
    'student_id',
    'status',
    'minutes_late',
    'note',
    'marked_by',
])]
final class AttendanceMark extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'minutes_late' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function withStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }
}
