<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case NonBinary = 'nonbinary';
    case Undisclosed = 'undisclosed';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Female',
            self::Male => 'Male',
            self::NonBinary => 'Non-binary',
            self::Undisclosed => 'Undisclosed',
        };
    }
}
