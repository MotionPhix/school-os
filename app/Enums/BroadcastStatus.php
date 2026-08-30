<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
