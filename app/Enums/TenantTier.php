<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Commercial tier of a tenant. Column type stays `string` so this enum
 * remains the single source of truth — add/rename entries here and the
 * database follows without a migration.
 */
enum TenantTier: string
{
    case Foundation = 'foundation';
    case Institution = 'institution';
    case Group = 'group';

    /** @return array<int, array{value:string,label:string,description:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'description' => $c->description(),
        ], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Foundation => 'Foundation',
            self::Institution => 'Institution',
            self::Group => 'Group / Multi-campus',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Foundation => 'Single small institution, up to 200 members.',
            self::Institution => 'Established school with full operations.',
            self::Group => 'Multi-campus group with consolidated reporting.',
        };
    }
}
