<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffCategory: string
{
    case Teaching = 'teaching';
    case Leadership = 'leadership';
    case Administration = 'administration';
    case Support = 'support';
    case Facilities = 'facilities';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Teaching => 'Teaching',
            self::Leadership => 'Leadership',
            self::Administration => 'Administration',
            self::Support => 'Support',
            self::Facilities => 'Facilities',
        };
    }
}
