<?php

declare(strict_types=1);

namespace App\Enums;

enum Weekday: string
{
    case Mon = 'mon';
    case Tue = 'tue';
    case Wed = 'wed';
    case Thu = 'thu';
    case Fri = 'fri';
    case Sat = 'sat';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Mon => 'Monday',
            self::Tue => 'Tuesday',
            self::Wed => 'Wednesday',
            self::Thu => 'Thursday',
            self::Fri => 'Friday',
            self::Sat => 'Saturday',
        };
    }
}
