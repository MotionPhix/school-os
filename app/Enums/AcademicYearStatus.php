<?php

declare(strict_types=1);

namespace App\Enums;

enum AcademicYearStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Closed = 'closed';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }

    /**
     * Legal lifecycle moves. A year is planned, opened, then closed;
     * a closed year may be reopened to Active to correct records, but
     * never returns to Planning once it has run.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planning => [self::Active],
            self::Active => [self::Closed],
            self::Closed => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
