<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime tenant-wide fan-out: pushed when an announcement is sent, so
 * every connected member's announcement list refreshes live. The channel
 * is private (`private-tenant.{id}`) and its auth callback is restricted
 * to tenant members, so the broadcast never leaks across tenants.
 */
final class AnnouncementPublished implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $announcementId,
        public readonly string $tenantId,
        public readonly string $title,
        public readonly string $authorName,
        public readonly string $sentAt,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'announcement.published';
    }

    /** @return array{announcement_id: string, tenant_id: string, title: string, author_name: string, sent_at: string} */
    public function broadcastWith(): array
    {
        return [
            'announcement_id' => $this->announcementId,
            'tenant_id' => $this->tenantId,
            'title' => $this->title,
            'author_name' => $this->authorName,
            'sent_at' => $this->sentAt,
        ];
    }
}
