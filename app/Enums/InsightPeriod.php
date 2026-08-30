<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Named time windows used by Insights & Reports.
 *
 * Every insights endpoint accepts either `period=<enum>` or an explicit
 * `from`/`to` pair. The enum keeps the UI honest: dashboards flip
 * between named windows, custom ranges are the exception.
 */
enum InsightPeriod: string
{
    case Last7d = 'last_7d';
    case Last30d = 'last_30d';
    case Last90d = 'last_90d';
    case TermToDate = 'term_to_date';
    case YearToDate = 'year_to_date';
    case Custom = 'custom';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Last7d => 'Last 7 days',
            self::Last30d => 'Last 30 days',
            self::Last90d => 'Last 90 days',
            self::TermToDate => 'Term to date',
            self::YearToDate => 'Year to date',
            self::Custom => 'Custom range',
        };
    }

    /**
     * @return array{from:CarbonImmutable,to:CarbonImmutable}
     */
    public function window(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return match ($this) {
            self::Last7d => ['from' => $now->subDays(6)->startOfDay(),  'to' => $now->endOfDay()],
            self::Last30d => ['from' => $now->subDays(29)->startOfDay(), 'to' => $now->endOfDay()],
            self::Last90d => ['from' => $now->subDays(89)->startOfDay(), 'to' => $now->endOfDay()],
            self::TermToDate => ['from' => $now->startOfMonth(),            'to' => $now->endOfDay()],
            self::YearToDate => ['from' => $now->startOfYear(),             'to' => $now->endOfDay()],
            self::Custom => ['from' => $now->subDays(29)->startOfDay(), 'to' => $now->endOfDay()],
        };
    }
}
