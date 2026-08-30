<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffEmploymentType: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case PartTime = 'part_time';
    case Visiting = 'visiting';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Contract => 'Contract',
            self::PartTime => 'Part-time',
            self::Visiting => 'Visiting',
        };
    }
}
