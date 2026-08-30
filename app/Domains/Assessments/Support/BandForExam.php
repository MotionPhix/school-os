<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Enums\GradeBand;

/**
 * Pure helper — derive a GradeBand from (score, max). Mirrors
 * src/contracts/assessments.ts::bandForExam so the API and the SPA
 * agree on grade bands before the frontend even has to compute.
 */
final class BandForExam
{
    public static function for(int $score, int $max): GradeBand
    {
        $pct = $max > 0 ? ($score / $max) * 100 : 0;

        return GradeBand::forTotal((int) round($pct));
    }
}
