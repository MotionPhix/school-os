<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunicationChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case InApp = 'in_app';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::InApp => 'In-app',
        };
    }
}
