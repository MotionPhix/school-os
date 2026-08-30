<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a UUID v7 primary key.
 *
 * UUID v7 is time-ordered, so it indexes almost as well as an auto-increment
 * bigint while remaining unguessable. Applied via boot hook so no controller
 * or seeder ever needs to generate an ID manually.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = static::newUuid();
            }
        });
    }

    /** UUID v7 when available (Laravel 11+), UUID v4 otherwise. */
    public static function newUuid(): string
    {
        return method_exists(Str::class, 'uuid7')
            ? (string) Str::uuid7()
            : (string) Str::uuid();
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
