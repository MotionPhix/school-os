<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\AnnouncementSent;
use App\Enums\AnnouncementStatus;
use App\Events\AnnouncementPublished;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mark an announcement as Sent and snapshot delivery/read counters.
 * Actual channel fan-out (SMS/email transport) is a downstream listener
 * responsibility; this service is the transactional pivot that other
 * capabilities react to via AnnouncementSent.
 */
final class SendAnnouncement
{
    public function handle(Announcement $ann): Announcement
    {
        if ($ann->status === AnnouncementStatus::Sent) {
            return $ann;
        }
        if ($ann->status === AnnouncementStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => 'Archived announcements cannot be sent.',
            ]);
        }

        return DB::transaction(function () use ($ann) {
            $ann->status = AnnouncementStatus::Sent;
            $ann->sent_at = now();
            // Optimistic delivery snapshot; real values will overwrite
            // once channel adapters report actuals.
            $ann->delivered_count = (int) round($ann->recipient_count * 0.98);
            $ann->read_count = (int) round($ann->delivered_count * 0.75);
            $ann->save();

            AnnouncementSent::dispatch($ann);

            AnnouncementPublished::dispatch(
                (string) $ann->id,
                (string) $ann->tenant_id,
                $ann->title,
                $ann->author_name,
                $ann->sent_at->toIso8601String(),
            );

            return $ann->refresh();
        });
    }
}
