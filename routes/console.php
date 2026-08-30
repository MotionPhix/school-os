<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
|
| Requires a single cron entry on the server:
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Identity housekeeping: expire stale invitations, purge dead invitation
// rows, and deactivate dormant self-serve signups.
Schedule::command('schoolos:cleanup-accounts')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->onOneServer();

// Observability: alert platform operators about broadcast delivery
// failures (deduped per broadcast via delivery_alerted_at).
Schedule::command('schoolos:check-broadcast-deliveries')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
