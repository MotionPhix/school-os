<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Support;

use App\Models\AcademicYear;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

/**
 * Generates the human-readable Application reference (e.g. APP-2026-00042).
 *
 * Sequence is derived per (tenant, academic year) so numbering restarts
 * cleanly each intake cycle. A row-level lock over `applications` keeps
 * concurrent inserts from colliding on the sequence.
 */
final class ApplicationReference
{
    public static function generate(string $tenantId, AcademicYear $year): string
    {
        $pattern = (string) config('admissions.reference.pattern', 'APP-{year}-{seq}');
        $padding = (int) config('admissions.reference.sequence_padding', 5);

        $yearToken = self::yearToken($year);

        $seq = DB::transaction(function () use ($tenantId, $year): int {
            $count = Application::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('academic_year_id', $year->id)
                ->lockForUpdate()
                ->count();

            return $count + 1;
        });

        return strtr($pattern, [
            '{year}' => $yearToken,
            '{seq}' => mb_str_pad((string) $seq, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    private static function yearToken(AcademicYear $year): string
    {
        if (! empty($year->starts_on)) {
            return (string) $year->starts_on->format('Y');
        }
        // Fall back to the label's leading 4-digit run, else the current year.
        if (preg_match('/\d{4}/', (string) ($year->label ?? ''), $m) === 1) {
            return $m[0];
        }

        return (string) now()->year;
    }
}
