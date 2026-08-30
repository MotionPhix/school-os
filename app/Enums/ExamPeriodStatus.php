<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamPeriodStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Closed => 'Closed',
        };
    }

    /** Closed periods lock all exams they contain. */
    public function isLocked(): bool
    {
        return $this === self::Closed;
    }

    /** Valid transitions — mirrors PERIOD_TRANSITIONS in the SPA contracts. */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Scheduled,
            self::Scheduled => in_array($next, [self::Draft, self::InProgress], true),
            self::InProgress => $next === self::Closed,
            self::Closed => $next === self::InProgress,
        };
    }
}
