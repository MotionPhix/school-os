<?php

declare(strict_types=1);

namespace App\Enums;

enum GuardianStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Inactive = 'inactive';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Invited => 'Invited',
            self::Inactive => 'Inactive',
        };
    }
}
