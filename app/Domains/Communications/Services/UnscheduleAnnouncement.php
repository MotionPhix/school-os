<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pull a scheduled announcement back to draft before its send time.
 * Mirrors `communications.announcements.unschedule` on the frontend.
 */
final class UnscheduleAnnouncement
{
    public function handle(Announcement $ann): Announcement
    {
        if ($ann->status !== AnnouncementStatus::Scheduled) {
            throw ValidationException::withMessages([
                'status' => 'Only scheduled announcements can be returned to draft.',
            ]);
        }

        return DB::transaction(function () use ($ann) {
            $ann->status = AnnouncementStatus::Draft;
            $ann->scheduled_for = null;
            $ann->save();

            return $ann->refresh();
        });
    }
}
