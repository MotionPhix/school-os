<?php

declare(strict_types=1);

use App\Support\RealtimeChannels;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Realtime channels (Phase 7). Definitions live in
| App\Support\RealtimeChannels::definitions() so tests can register the
| same closures on a recording broadcaster. Private per-user channels
| carry feed badge pushes and broadcast progress ticks; the tenant
| channel is open to any member (future tenant-wide fan-out).
|
*/

foreach (RealtimeChannels::definitions() as $name => $callback) {
    Broadcast::channel($name, $callback);
}
