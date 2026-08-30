<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageThreadStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $subject
 * @property MessageThreadStatus $status
 * @property string|null $student_id
 * @property string|null $student_name
 * @property string $last_message_preview
 * @property Carbon|null $last_message_at
 * @property int $unread_count
 */
#[Fillable([
    'tenant_id', 'subject', 'status', 'student_id', 'student_name',
    'last_message_preview', 'last_message_at', 'unread_count',
])]
final class MessageThread extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'comm_message_threads';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ThreadParticipant::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ThreadMessage::class, 'thread_id')->orderBy('sent_at');
    }

    protected function casts(): array
    {
        return [
            'status' => MessageThreadStatus::class,
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', MessageThreadStatus::Open->value);
    }
}
