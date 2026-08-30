<?php

declare(strict_types=1);

namespace App\Enums;

enum CampusStatus: string
{
    case Operational = 'operational';
    case UnderConstruction = 'under_construction';
    case Closed = 'closed';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::UnderConstruction => 'Under construction',
            self::Closed => 'Closed',
        };
    }
}
