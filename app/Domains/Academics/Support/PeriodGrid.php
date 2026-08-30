<?php

declare(strict_types=1);

namespace App\Domains\Academics\Support;

/**
 * Derives the default `starts_at`/`ends_at` for a period index using
 * the grid configured in `config/academics.timetable`.
 */
final class PeriodGrid
{
    /** @return array{starts_at:string, ends_at:string} */
    public static function forPeriod(int $period): array
    {
        $config = (array) config('academics.timetable', []);
        [$startH, $startM] = array_map('intval', explode(':', (string) ($config['day_start'] ?? '08:00'), 2));
        $length = (int) ($config['period_minutes'] ?? 40);
        $gap = (int) ($config['period_gap_minutes'] ?? 5);

        $base = ($startH * 60) + $startM;
        $start = $base + max(0, $period - 1) * ($length + $gap);
        $end = $start + $length;

        return [
            'starts_at' => self::fmt($start),
            'ends_at' => self::fmt($end),
        ];
    }

    private static function fmt(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
