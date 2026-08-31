<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
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
        static::creating(function (Model $model): void {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = static::newUuid();
            }
        });
    }

    public static function newUuid(): string
    {
        return (string) Str::uuid7();
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
