<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubjectCategory;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Subject — tenant-level catalog entry. Referenced by CourseSection.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property SubjectCategory $category
 * @property array<int,string> $stages
 * @property bool $is_core
 * @property int $credit_hours
 * @property string|null $description
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'code',
    'name',
    'category',
    'stages',
    'is_core',
    'credit_hours',
    'description',
])]
final class Subject extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class);
    }

    protected function casts(): array
    {
        return [
            'category' => SubjectCategory::class,
            'stages' => 'array',
            'is_core' => 'boolean',
            'credit_hours' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function core(Builder $query): void
    {
        $query->where('is_core', true);
    }

    #[Scope]
    protected function forStage(Builder $query, string $stage): void
    {
        $query->whereJsonContains('stages', $stage);
    }
}
