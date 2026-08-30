<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Separated = 'separated';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Separated => 'Separated',
        };
    }
}
