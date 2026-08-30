<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Enums\AnnouncementStatus;
use App\Enums\BroadcastStatus;
use App\Enums\MessageThreadStatus;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Batch operations over announcements, threads and broadcasts.
 *
 * Mirrors the bulk verbs in src/lib/verbs/communications.ts. Rows are
 * applied through the single-record services so business events keep
 * firing; lifecycle violations are skipped with a reason rather than
 * failing the whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkCommunicationsAction
{
    public function __construct(
        private readonly SendAnnouncement $send,
        private readonly ArchiveAnnouncement $archive,
        private readonly UnscheduleAnnouncement $unschedule,
        private readonly SetThreadStatus $setStatus,
        private readonly MarkThreadRead $markRead,
        private readonly StartBroadcast $start,
        private readonly CancelBroadcast $cancel,
    ) {}

    /**
     * @param  array<int,string>  $ids
     * @param  'send'|'archive'|'unschedule'|'delete'  $action
     * @return BulkResult
     */
    public function announcements(array $ids, string $action): array
    {
        $skipped = [];
        $affected = 0;

        foreach (Announcement::query()->whereIn('id', $ids)->get() as $ann) {
            try {
                switch ($action) {
                    case 'send':
                        $this->send->handle($ann);
                        break;

                    case 'archive':
                        if ($ann->status === AnnouncementStatus::Archived) {
                            $skipped[] = "{$ann->title}: already archived.";

                            continue 2;
                        }
                        $this->archive->handle($ann);
                        break;

                    case 'unschedule':
                        $this->unschedule->handle($ann);
                        break;

                    default:
                        if ($ann->status === AnnouncementStatus::Sent) {
                            $skipped[] = "{$ann->title}: sent notices are retained for audit — archive instead.";

                            continue 2;
                        }
                        $ann->delete();
                }

                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$ann->title}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @param  'resolve'|'snooze'|'reopen'|'mark_read'  $action
     * @return BulkResult
     */
    public function threads(array $ids, string $action): array
    {
        $target = [
            'resolve' => MessageThreadStatus::Resolved,
            'snooze' => MessageThreadStatus::Snoozed,
            'reopen' => MessageThreadStatus::Open,
        ];

        $skipped = [];
        $affected = 0;

        foreach (MessageThread::query()->whereIn('id', $ids)->get() as $thread) {
            try {
                if ($action === 'mark_read') {
                    $this->markRead->handle($thread);
                } else {
                    $next = $target[$action];
                    if ($thread->status === $next) {
                        $skipped[] = "{$thread->subject}: already {$next->value}.";

                        continue;
                    }
                    $this->setStatus->handle($thread, $next);
                }

                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$thread->subject}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @param  'start'|'cancel'|'delete'  $action
     * @return BulkResult
     */
    public function broadcasts(array $ids, string $action, ?User $actor = null): array
    {
        $skipped = [];
        $affected = 0;

        foreach (Broadcast::query()->whereIn('id', $ids)->get() as $b) {
            try {
                switch ($action) {
                    case 'start':
                        $this->start->handle($b);
                        break;

                    case 'cancel':
                        $this->cancel->handle($b);
                        break;

                    default:
                        if ($b->status !== BroadcastStatus::Draft) {
                            $skipped[] = "{$b->name}: only drafts can be deleted.";

                            continue 2;
                        }
                        $b->delete();
                }

                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$b->name}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }
}
