<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Domains\People\Events\StudentCreated;
use App\Domains\People\Events\StudentStatusChanged;
use App\Domains\People\Events\StudentUpdated;
use App\Domains\People\Support\AvatarInitials;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a Student. `avatar_initials` is always derived from
 * `full_name` so the two stay consistent. A status transition emits
 * StudentStatusChanged in addition to the ordinary Updated event.
 */
final class WriteStudent
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?Student $existing = null): Student
    {
        return DB::transaction(function () use ($data, $existing): Student {
            $creating = $existing === null;
            $student = $existing ?? new Student;
            $priorStatus = $creating ? null : $student->status;

            if (isset($data['full_name'])) {
                $data['avatar_initials'] = AvatarInitials::from((string) $data['full_name']);
            }

            $student->fill($data);
            $student->save();
            $student->refresh();

            if ($creating) {
                StudentCreated::dispatch($student);
            } else {
                StudentUpdated::dispatch($student);
                if ($priorStatus !== null && $priorStatus !== $student->status) {
                    StudentStatusChanged::dispatch(
                        $student,
                        $priorStatus,
                        $student->status,
                    );
                }
            }

            return $student;
        });
    }

    public function setStatus(Student $student, StudentStatus $status): Student
    {
        $prior = $student->status;
        if ($prior === $status) {
            return $student;
        }

        return DB::transaction(function () use ($student, $status, $prior): Student {
            $student->status = $status;
            if ($status === StudentStatus::Enrolled && $student->enrolled_on === null) {
                $student->enrolled_on = now()->toDateString();
            }
            $student->save();
            $student->refresh();

            StudentStatusChanged::dispatch($student, $prior, $status);

            return $student;
        });
    }
}
