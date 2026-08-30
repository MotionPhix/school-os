<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThreadParticipantRole;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $thread_id
 * @property string|null $author_id
 * @property string $author_name
 * @property ThreadParticipantRole $author_role
 * @property string $body
 * @property Carbon $sent_at
 * @property bool $read
 */
#[Fillable([
    'tenant_id', 'thread_id', 'author_id', 'author_name', 'author_role',
    'body', 'sent_at', 'read',
])]
final class ThreadMessage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'comm_thread_messages';

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    protected function casts(): array
    {
        return [
            'author_role' => ThreadParticipantRole::class,
            'sent_at' => 'datetime',
            'read' => 'boolean',
        ];
    }
}
