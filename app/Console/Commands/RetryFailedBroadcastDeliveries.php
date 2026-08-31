<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Communications resilience: retries failed broadcast deliveries with
 * exponential backoff, then dead-letters a broadcast once its retry budget
 * is exhausted (delivery_dead_lettered_at set, failures kept for triage).
 * Scheduled every 15 minutes via routes/console.php.
 *
 * The channel adapter currently reports aggregate counts, so each retry
 * recovers a configurable share of the remaining failures (simulated
 * receipts) — the same placeholder the completion step uses. With real
 * per-recipient acks this command would re-attempt actual deliveries; the
 * backoff/dead-letter machinery is identical.
 */
final class RetryFailedBroadcastDeliveries extends Command
{
    protected $signature = 'schoolos:retry-broadcast-deliveries';

    protected $description = 'Retry failed broadcast deliveries with backoff; dead-letter exhausted ones.';

    public function handle(TenantContext $tenant): int
    {
        $baseRaw = config('observability.broadcast_delivery_retry.base_interval_minutes');
        $base = is_int($baseRaw) ? max(1, $baseRaw) : 15;
        $factorRaw = config('observability.broadcast_delivery_retry.backoff_factor');
        $factor = is_int($factorRaw) ? max(2, $factorRaw) : 2;
        $capRaw = config('observability.broadcast_delivery_retry.max_interval_minutes');
        $cap = is_int($capRaw) ? max($base, $capRaw) : 240;
        $maxRaw = config('observability.broadcast_delivery_retry.max_retries');
        $maxRetries = is_int($maxRaw) ? max(0, $maxRaw) : 3;
        $numRaw = config('observability.broadcast_delivery_retry.recovery_numerator');
        $num = is_int($numRaw) ? max(1, $numRaw) : 1;
        $denRaw = config('observability.broadcast_delivery_retry.recovery_denominator');
        $den = is_int($denRaw) ? max(1, $denRaw) : 2;

        $retried = 0;
        $deadLettered = 0;

        foreach (Tenant::query()->cursor() as $tenantModel) {
            $tenant->set($tenantModel->id);

            $now = Carbon::now();

            $candidates = Broadcast::query()
                ->where('status', BroadcastStatus::Completed->value)
                ->where('failed_count', '>', 0)
                ->whereNull('delivery_dead_lettered_at')
                ->where(function ($q) use ($now): void {
                    $q->whereNull('delivery_next_retry_at')
                        ->orWhere('delivery_next_retry_at', '<=', $now);
                })
                ->get();

            foreach ($candidates as $broadcast) {
                if ($broadcast->delivery_retry_count >= $maxRetries) {
                    // Retry budget exhausted → dead-letter (keep the failure
                    // breakdown for triage; the alert command picks this up).
                    $broadcast->forceFill([
                        'delivery_dead_lettered_at' => $now,
                    ])->save();
                    $deadLettered++;

                    continue;
                }

                $failed = (int) $broadcast->failed_count;
                // ceil(failed * num / den) — exact integer division.
                $recovered = intdiv($failed * $num + $den - 1, $den);

                $broadcast->forceFill([
                    'delivered_count' => (int) $broadcast->delivered_count + $recovered,
                    'failed_count' => max(0, $failed - $recovered),
                    'delivery_retry_count' => (int) $broadcast->delivery_retry_count + 1,
                    'delivery_next_retry_at' => $now->copy()->addMinutes(
                        min($cap, $base * $factor ** ((int) $broadcast->delivery_retry_count + 1)),
                    ),
                ])->save();

                $retried++;
            }
        }

        $tenant->clear();

        $this->info("Broadcast deliveries: {$retried} retried, {$deadLettered} dead-lettered.");

        return self::SUCCESS;
    }
}
