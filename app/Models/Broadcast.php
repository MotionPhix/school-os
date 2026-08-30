<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastStatus;
use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Enums\CurrencyCode;
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
 * @property string $name
 * @property CommunicationChannel $channel
 * @property CommunicationAudience $audience
 * @property string $audience_label
 * @property string $template_snippet
 * @property BroadcastStatus $status
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $delivery_alerted_at
 * @property int $recipient_count
 * @property int $delivered_count
 * @property int $failed_count
 * @property int $cost_minor
 * @property CurrencyCode $currency
 */
#[Fillable([
    'tenant_id', 'name', 'channel', 'audience', 'audience_label',
    'template_snippet', 'status', 'scheduled_for', 'started_at',
    'completed_at', 'delivery_alerted_at', 'recipient_count', 'delivered_count', 'failed_count',
    'cost_minor', 'currency', 'created_by',
])]
final class Broadcast extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'comm_broadcasts';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'audience' => CommunicationAudience::class,
            'status' => BroadcastStatus::class,
            'currency' => CurrencyCode::class,
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivery_alerted_at' => 'datetime',
            'recipient_count' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
            'cost_minor' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [
            BroadcastStatus::Queued->value,
            BroadcastStatus::Sending->value,
        ]);
    }
}
