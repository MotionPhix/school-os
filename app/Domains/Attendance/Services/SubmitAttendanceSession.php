<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Events\AttendanceSessionSubmitted;
use App\Domains\Attendance\Support\SessionCounts;
use App\Enums\AttendanceSessionStatus;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;

/**
 * Lock a draft session: recomputes counts, sets `taken_at`, flips
 * status → submitted, dispatches the business event.
 */
final class SubmitAttendanceSession
{
    public function handle(AttendanceSession $session): AttendanceSession
    {
        return DB::transaction(function () use ($session): AttendanceSession {
            if ($session->status === AttendanceSessionStatus::Submitted) {
                return $session;
            }

            $marks = $session->marks()->get();
            SessionCounts::apply($session, $marks);
            $session->status = AttendanceSessionStatus::Submitted;
            $session->taken_at = $session->taken_at ?? now();
            $session->save();

            AttendanceSessionSubmitted::dispatch($session);

            return $session->refresh();
        });
    }
}
