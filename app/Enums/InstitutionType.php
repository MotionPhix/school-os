<?php

declare(strict_types=1);

namespace App\Enums;

enum InstitutionType: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case K12 = 'k12';
    case Tertiary = 'tertiary';
    case Vocational = 'vocational';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Secondary => 'Secondary',
            self::K12 => 'K–12',
            self::Tertiary => 'Tertiary',
            self::Vocational => 'Vocational',
        };
    }
}
