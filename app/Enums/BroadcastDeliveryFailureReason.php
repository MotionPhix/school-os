<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery failure taxonomy for broadcasts. Kept deliberately small so
 * operators can triage without a full per-recipient delivery ledger;
 * counts per reason are stored on the broadcast row (`failure_reasons`).
 */
enum BroadcastDeliveryFailureReason: string
{
    case Offline = 'offline';
    case ConnectionFailed = 'connection_failed';
    case Unauthorized = 'unauthorized';
    case Timeout = 'timeout';
    case Rejected = 'rejected';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Recipient offline',
            self::ConnectionFailed => 'Connection failed',
            self::Unauthorized => 'Unauthorized channel access',
            self::Timeout => 'Delivery timeout',
            self::Rejected => 'Rejected by gateway',
        };
    }
}
