<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentStage: string
{
    case EarlyYears = 'early_years';
    case Primary = 'primary';
    case Junior = 'junior';
    case Senior = 'senior';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::EarlyYears => 'Early years',
            self::Primary => 'Primary',
            self::Junior => 'Junior',
            self::Senior => 'Senior',
        };
    }
}
