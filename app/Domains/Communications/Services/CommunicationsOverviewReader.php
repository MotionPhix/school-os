<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Enums\AnnouncementStatus;
use App\Enums\BroadcastStatus;
use App\Enums\CommunicationChannel;
use App\Enums\MessageThreadStatus;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\MessageThread;

/**
 * Communications overview — the KPI block shown on the capability
 * dashboard. Answers "how many messages went out?", "what's unread?",
 * and "how much did SMS cost this month?" from denormalised counters
 * so the dashboard is a handful of aggregate queries.
 */
final class CommunicationsOverviewReader
{
    /**
     * @return array{
     *   announcements_sent_30d:int,
     *   announcements_scheduled:int,
     *   threads_open:int,
     *   threads_unread:int,
     *   broadcasts_active:int,
     *   sms_cost_month_minor:int,
     *   currency:string,
     *   delivery_rate_pct:int,
     * }
     */
    public function read(): array
    {
        $since30 = now()->subDays(30);
        $sentSince = Announcement::query()
            ->where('status', AnnouncementStatus::Sent->value)
            ->where('sent_at', '>=', $since30)
            ->get(['recipient_count', 'delivered_count']);

        $recipients = (int) $sentSince->sum('recipient_count');
        $delivered = (int) $sentSince->sum('delivered_count');

        $startOfMonth = now()->startOfMonth();
        $smsCost = (int) Broadcast::query()
            ->where('channel', CommunicationChannel::Sms->value)
            ->where('created_at', '>=', $startOfMonth)
            ->sum('cost_minor');

        return [
            'announcements_sent_30d' => $sentSince->count(),
            'announcements_scheduled' => (int) Announcement::query()
                ->where('status', AnnouncementStatus::Scheduled->value)
                ->count(),
            'threads_open' => (int) MessageThread::query()
                ->where('status', MessageThreadStatus::Open->value)
                ->count(),
            'threads_unread' => (int) MessageThread::query()->sum('unread_count'),
            'broadcasts_active' => (int) Broadcast::query()
                ->whereIn('status', [BroadcastStatus::Queued->value, BroadcastStatus::Sending->value])
                ->count(),
            'sms_cost_month_minor' => $smsCost,
            'currency' => (string) config('communications.currency', 'MWK'),
            'delivery_rate_pct' => $recipients > 0
                ? (int) round(($delivered / $recipients) * 100)
                : 0,
        ];
    }
}
