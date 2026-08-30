<?php

declare(strict_types=1);

namespace App\Enums;

enum TermStatus: string
{
    case Upcoming = 'upcoming';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
        };
    }

    /**
     * Legal lifecycle moves. A term is upcoming, starts, then completes;
     * a completed term may be reopened to In progress to correct marks.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Upcoming => [self::InProgress],
            self::InProgress => [self::Completed],
            self::Completed => [self::InProgress],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
