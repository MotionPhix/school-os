<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThreadParticipantRole;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $thread_id
 * @property string|null $user_id
 * @property string $name
 * @property ThreadParticipantRole $role
 * @property string $avatar_initials
 */
#[Fillable([
    'tenant_id', 'thread_id', 'user_id', 'name', 'role', 'avatar_initials',
])]
final class ThreadParticipant extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'comm_thread_participants';

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'role' => ThreadParticipantRole::class,
        ];
    }
}
