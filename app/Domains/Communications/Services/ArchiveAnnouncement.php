<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\AnnouncementArchived;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;

final class ArchiveAnnouncement
{
    public function handle(Announcement $ann): Announcement
    {
        if ($ann->status === AnnouncementStatus::Archived) {
            return $ann;
        }

        return DB::transaction(function () use ($ann) {
            $ann->status = AnnouncementStatus::Archived;
            $ann->save();

            AnnouncementArchived::dispatch($ann);

            return $ann->refresh();
        });
    }
}
