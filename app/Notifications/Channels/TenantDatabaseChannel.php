<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Events\FeedBadgeUpdated;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, extended to stamp `tenant_id` from the
 * request/job context (the stock channel instantiates the base
 * DatabaseNotification, so the model's tenant concern never fires) and to
 * push the recipient's unread-count badge over the realtime layer
 * (Phase 7 — FeedBadgeUpdated on `private-users.{id}`).
 */
final class TenantDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'tenant_id' => app(TenantContext::class)->id(),
        ];
    }

    public function send($notifiable, Notification $notification)
    {
        $result = parent::send($notifiable, $notification);

        if ($notifiable instanceof User) {
            FeedBadgeUpdated::dispatch($notifiable->id, $this->unreadCount($notifiable));
        }

        return $result;
    }

    private function unreadCount(User $user): int
    {
        $query = \App\Models\Notification::query()
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at');

        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->count();
    }
}
