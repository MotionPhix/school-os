<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Communications\Events\BroadcastDeliveryFailureDetected;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Observability: scans every tenant for completed broadcasts whose
 * delivery failure rate crossed the configured threshold and emits
 * BroadcastDeliveryFailureDetected (one alert per broadcast, deduped by
 * `delivery_alerted_at`). Scheduled hourly via routes/console.php.
 */
final class CheckBroadcastDeliveries extends Command
{
    protected $signature = 'schoolos:check-broadcast-deliveries';

    protected $description = 'Alert platform operators about broadcast delivery failures.';

    public function handle(TenantContext $tenant): int
    {
        $minFailed = config('observability.broadcast_delivery_alert.min_failed', 5);
        $maxFailureRate = config('observability.broadcast_delivery_alert.max_failure_rate', 0.10);
        $lookback = config('observability.broadcast_delivery_alert.lookback_hours', 24);

        if (! is_int($minFailed) || ! is_float($maxFailureRate) || ! is_int($lookback)) {
            $minFailed = 5;
            $maxFailureRate = 0.10;
            $lookback = 24;
        }

        $maxRetriesRaw = config('observability.broadcast_delivery_retry.max_retries');
        $maxRetries = is_int($maxRetriesRaw) ? max(0, $maxRetriesRaw) : 3;

        $alerts = 0;

        foreach (Tenant::query()->cursor() as $tenantModel) {
            $tenant->set($tenantModel->id);

            $since = Carbon::now()->subHours($lookback);

            // Only broadcasts that are dead-lettered or past their retry
            // budget are alerted — in-flight retries are not yet "failures".
            $candidates = Broadcast::query()
                ->where('status', BroadcastStatus::Completed->value)
                ->whereNull('delivery_alerted_at')
                ->where('completed_at', '>=', $since)
                ->where(fn ($q) => $q
                    ->whereNotNull('delivery_dead_lettered_at')
                    ->orWhere('delivery_retry_count', '>=', $maxRetries))
                ->get();

            foreach ($candidates as $broadcast) {
                $failed = (int) $broadcast->failed_count;
                $total = (int) $broadcast->recipient_count;

                if ($total === 0) {
                    continue;
                }

                $rate = $failed / $total;

                if ($failed >= $minFailed && $rate > $maxFailureRate) {
                    $broadcast->forceFill(['delivery_alerted_at' => Carbon::now()])->save();

                    BroadcastDeliveryFailureDetected::dispatch($broadcast);

                    $alerts++;
                }
            }
        }

        $tenant->clear();

        $this->info("Checked broadcast deliveries; dispatched {$alerts} failure alert(s).");

        return self::SUCCESS;
    }
}
