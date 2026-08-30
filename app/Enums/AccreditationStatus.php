<?php

declare(strict_types=1);

namespace App\Enums;

enum AccreditationStatus: string
{
    case Accredited = 'accredited';
    case Provisional = 'provisional';
    case Pending = 'pending';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Accredited => 'Accredited',
            self::Provisional => 'Provisional',
            self::Pending => 'Pending',
        };
    }
}
