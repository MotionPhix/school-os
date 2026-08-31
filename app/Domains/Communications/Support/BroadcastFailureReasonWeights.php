<?php

declare(strict_types=1);

namespace App\Domains\Communications\Support;

/**
 * Integer-only distribution of a failure count across the reason taxonomy
 * (no floats — mirrors the finance integer-minor principle). The remainder
 * after proportional allocation goes to the largest bucket so the buckets
 * always sum exactly to the total.
 */
final class BroadcastFailureReasonWeights
{
    /**
     * @param  array<mixed, mixed>  $weights  reason => weight (non-int entries ignored)
     * @return array<string, int> reason => count, summing exactly to $total
     */
    public static function distribute(int $total, array $weights): array
    {
        if ($total <= 0) {
            return [];
        }

        $clean = [];
        foreach ($weights as $reason => $weight) {
            if (is_string($reason) && is_int($weight) && $weight > 0) {
                $clean[$reason] = $weight;
            }
        }

        if ($clean === []) {
            return [];
        }

        $sum = array_sum($clean);
        $out = [];
        $allocated = 0;
        foreach ($clean as $reason => $weight) {
            $share = intdiv($total * $weight, $sum);
            $out[$reason] = $share;
            $allocated += $share;
        }

        if ($allocated < $total) {
            $largest = (string) array_keys($clean, max($clean), true)[0];
            $out[$largest] += $total - $allocated;
        }

        return $out;
    }
}
