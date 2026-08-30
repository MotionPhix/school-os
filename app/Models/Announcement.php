<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementStatus;
use App\Enums\CommunicationAudience;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $title
 * @property string $body
 * @property CommunicationAudience $audience
 * @property string $audience_label
 * @property array<int,string> $channels
 * @property AnnouncementStatus $status
 * @property string|null $author_id
 * @property string $author_name
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property int $recipient_count
 * @property int $delivered_count
 * @property int $read_count
 */
#[Fillable([
    'tenant_id', 'title', 'body', 'audience', 'audience_label', 'channels',
    'status', 'author_id', 'author_name', 'scheduled_for', 'sent_at',
    'recipient_count', 'delivered_count', 'read_count',
])]
final class Announcement extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'comm_announcements';

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected function casts(): array
    {
        return [
            'audience' => CommunicationAudience::class,
            'status' => AnnouncementStatus::class,
            'channels' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'recipient_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
        ];
    }

    #[Scope]
    protected function status(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->where('status', AnnouncementStatus::Scheduled->value);
    }

    #[Scope]
    protected function sentSince(Builder $query, string $sinceIso): void
    {
        $query->where('status', AnnouncementStatus::Sent->value)
            ->where('sent_at', '>=', $sinceIso);
    }
}
