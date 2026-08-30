<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Marking = 'marking';
    case Published = 'published';

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
            self::Marking => 'Marking',
            self::Published => 'Published',
        };
    }

    /** Published exams cannot have their scores edited. */
    public function isLocked(): bool
    {
        return $this === self::Published;
    }

    /** Valid state transitions — mirror the SPA's assessments state machine. */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Scheduled, self::Marking], true),
            self::Scheduled => in_array($next, [self::Draft, self::Marking], true),
            self::Marking => in_array($next, [self::Scheduled, self::Published], true),
            self::Published => false,
        };
    }
}
