<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Events\CourseEnrollmentAdded;
use App\Domains\Academics\Events\CourseEnrollmentRemoved;
use App\Models\CourseSection;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EnrollStudentInCourse
{
    public function enroll(CourseSection $section, Student $student): void
    {
        DB::transaction(function () use ($section, $student): void {
            // Duplicate enrollment is a client error, not a silent no-op.
            if ($section->students()->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student is already enrolled in the section.',
                ]);
            }

            $current = $section->students()->count();
            if ($current >= $section->capacity) {
                throw ValidationException::withMessages([
                    'student_id' => "Section is at capacity ({$section->capacity}).",
                ]);
            }

            $section->students()->syncWithoutDetaching([
                $student->id => [
                    'tenant_id' => $section->tenant_id,
                    'enrolled_at' => now(),
                ],
            ]);

            CourseEnrollmentAdded::dispatch($section, $student);
        });
    }

    public function drop(CourseSection $section, Student $student): void
    {
        DB::transaction(function () use ($section, $student): void {
            $section->students()->detach($student->id);
            // Also purge gradebook entries for this student in this section.
            $section->gradebookEntries()->where('student_id', $student->id)->delete();
            CourseEnrollmentRemoved::dispatch($section, $student);
        });
    }
}
