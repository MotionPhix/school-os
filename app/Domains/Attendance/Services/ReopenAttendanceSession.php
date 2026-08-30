<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Events\AttendanceSessionReopened;
use App\Enums\AttendanceSessionStatus;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Unlock a submitted register so a correction can be made.
 *
 * `taken_at` is deliberately preserved — the original take time stays on
 * the record for audit; resubmission does not overwrite it.
 */
final class ReopenAttendanceSession
{
    public function handle(AttendanceSession $session): AttendanceSession
    {
        return DB::transaction(function () use ($session): AttendanceSession {
            if ($session->status !== AttendanceSessionStatus::Submitted) {
                throw ValidationException::withMessages([
                    'session_id' => 'Only submitted registers can be reopened.',
                ]);
            }

            $session->status = AttendanceSessionStatus::Draft;
            $session->save();

            AttendanceSessionReopened::dispatch($session);

            return $session->refresh();
        });
    }
}
