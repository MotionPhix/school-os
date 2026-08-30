<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PipelineStage;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only timeline entry for an Application. Every stage transition,
 * offer send, and enrollment mints one of these.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property PipelineStage|null $from_stage
 * @property PipelineStage $to_stage
 * @property string|null $note
 * @property string $actor_name
 * @property string|null $actor_id
 * @property Carbon $occurred_at
 */
#[Fillable([
    'tenant_id',
    'application_id',
    'from_stage',
    'to_stage',
    'note',
    'actor_name',
    'actor_id',
    'occurred_at',
])]
final class ApplicationStageEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return [
            'from_stage' => PipelineStage::class,
            'to_stage' => PipelineStage::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
