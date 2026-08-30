<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Snoozed = 'snoozed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Snoozed => 'Snoozed',
        };
    }
}
