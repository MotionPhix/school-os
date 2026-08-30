<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Support\CommunicationsPermission;
use App\Enums\AnnouncementStatus;
use App\Enums\InvitationStatus;
use App\Enums\InvoiceStatus;
use App\Models\Announcement;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Composes the workspace notification feed for the active tenant.
 *
 * The feed is derived (not stored): overdue invoices, unread message threads,
 * scheduled announcements and pending invitations. Each source is gated by the
 * caller's permission keys, so the payload never leaks a capability the user
 * cannot open.
 */
final class NotificationFeedReader
{
    private const LIMIT_PER_SOURCE = 15;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CommunicationsPermission $perm,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function read(User $user): array
    {
        $tenantId = $this->tenant->id();
        if ($tenantId === null) {
            return [];
        }

        $items = [];

        if ($this->perm->has($user, 'finance.invoices.read')) {
            $invoices = Invoice::query()
                ->where('tenant_id', $tenantId)
                ->where('status', InvoiceStatus::Overdue)
                ->orderByDesc('due_on')
                ->limit(self::LIMIT_PER_SOURCE)
                ->get();

            foreach ($invoices as $invoice) {
                $amount = number_format($invoice->balance_minor / 100, 2);
                $items[] = [
                    'id' => 'fin:'.$invoice->id,
                    'kind' => 'finance',
                    'title' => "Invoice {$invoice->number} is overdue",
                    'body' => sprintf(
                        '%s · %s %s outstanding since %s',
                        $invoice->student_name,
                        $invoice->currency->value,
                        $amount,
                        $invoice->due_on?->toDateString() ?? '',
                    ),
                    'href' => "/finance/invoices/{$invoice->id}",
                    'at' => ($invoice->updated_at ?? $invoice->due_on)?->toIso8601String(),
                ];
            }
        }

        if ($this->perm->has($user, 'communications.threads.read')) {
            $threads = MessageThread::query()
                ->where('tenant_id', $tenantId)
                ->where('unread_count', '>', 0)
                ->orderByDesc('last_message_at')
                ->limit(self::LIMIT_PER_SOURCE)
                ->get();

            foreach ($threads as $thread) {
                $items[] = [
                    'id' => 'msg:'.$thread->id,
                    'kind' => 'message',
                    'title' => "{$thread->unread_count} unread in \"{$thread->subject}\"",
                    'body' => (string) $thread->last_message_preview,
                    'href' => '/communications/threads',
                    'at' => $thread->last_message_at?->toIso8601String(),
                ];
            }
        }

        if ($this->perm->has($user, 'communications.announcements.read')) {
            $announcements = Announcement::query()
                ->where('tenant_id', $tenantId)
                ->where('status', AnnouncementStatus::Scheduled)
                ->whereNotNull('scheduled_for')
                ->orderBy('scheduled_for')
                ->limit(self::LIMIT_PER_SOURCE)
                ->get();

            foreach ($announcements as $announcement) {
                $items[] = [
                    'id' => 'ann:'.$announcement->id,
                    'kind' => 'announcement',
                    'title' => "Scheduled: {$announcement->title}",
                    'body' => sprintf(
                        '%s · goes out %s',
                        $announcement->audience_label,
                        $announcement->scheduled_for?->toDayDateTimeString() ?? '',
                    ),
                    'href' => '/communications/announcements',
                    'at' => $announcement->scheduled_for?->toIso8601String(),
                ];
            }
        }

        if ($this->perm->has($user, 'identity.invitations.read')) {
            $invitations = Invitation::query()
                ->where('tenant_id', $tenantId)
                ->where('status', InvitationStatus::Pending)
                ->orderByDesc('updated_at')
                ->limit(self::LIMIT_PER_SOURCE)
                ->get();

            foreach ($invitations as $invitation) {
                $items[] = [
                    'id' => 'idn:'.$invitation->id,
                    'kind' => 'identity',
                    'title' => "Invitation pending — {$invitation->email}",
                    'body' => sprintf(
                        'Expires %s',
                        $invitation->expires_at?->toFormattedDateString() ?? 'soon',
                    ),
                    'href' => '/identity/invitations',
                    'at' => ($invitation->updated_at ?? $invitation->expires_at)?->toIso8601String(),
                ];
            }
        }

        usort($items, static fn (array $a, array $b) => strcmp((string) $b['at'], (string) $a['at']));

        return $items;
    }
}
