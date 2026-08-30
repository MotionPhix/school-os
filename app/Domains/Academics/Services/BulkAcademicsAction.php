<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Enums\CourseStatus;
use App\Models\CourseSection;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bulk operations for the Academics catalog.
 *
 * Every row is applied through the ordinary single-record services so business
 * events keep firing; rows that violate a guard are skipped with a reason
 * instead of failing the whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkAcademicsAction
{
    public function __construct(
        private readonly WriteSubject $writeSubject,
        private readonly WriteCourseSection $writeCourse,
    ) {}

    /**
     * @param  array<int,string>  $ids
     * @param  array<string,mixed>  $patch
     * @return BulkResult
     */
    public function updateSubjects(array $ids, array $patch): array
    {
        $subjects = Subject::query()->whereIn('id', $ids)->get();
        $affected = 0;

        foreach ($subjects as $subject) {
            $this->writeSubject->handle($patch, $subject);
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => []];
    }

    /**
     * A subject cannot be deleted while a non-archived course section still
     * references it — the catalog is the anchor of the academic record.
     *
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function deleteSubjects(array $ids): array
    {
        $subjects = Subject::query()->whereIn('id', $ids)->withCount([
            'courseSections as live_sections_count' => fn ($q) => $q
                ->where('status', '!=', CourseStatus::Archived->value),
        ])->get();

        $skipped = [];
        $affected = 0;

        foreach ($subjects as $subject) {
            if ($subject->live_sections_count > 0) {
                $skipped[] = "{$subject->code}: {$subject->live_sections_count} live course section(s) still reference it.";

                continue;
            }

            $subject->delete();
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function setCourseStatus(array $ids, CourseStatus $status): array
    {
        $sections = CourseSection::query()
            ->whereIn('id', $ids)
            ->withCount('timetableSlots')
            ->get();

        $skipped = [];
        $affected = 0;

        foreach ($sections as $section) {
            if ($section->status === $status) {
                continue;
            }

            try {
                $this->assertPublishable($section, $status);
            } catch (ValidationException $e) {
                $skipped[] = "{$section->section_label}: ".$e->getMessage();

                continue;
            }

            $this->writeCourse->handle(['status' => $status->value], $section);
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function deleteCourses(array $ids): array
    {
        $sections = CourseSection::query()
            ->whereIn('id', $ids)
            ->withCount(['enrollments', 'gradebookEntries'])
            ->get();

        $skipped = [];
        $affected = 0;

        foreach ($sections as $section) {
            if ($section->enrollments_count > 0) {
                $skipped[] = "{$section->section_label}: {$section->enrollments_count} student(s) are still enrolled.";

                continue;
            }

            if ($section->gradebook_entries_count > 0) {
                $skipped[] = "{$section->section_label}: carries gradebook history — archive it instead.";

                continue;
            }

            $section->delete();
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * Copy a section: teacher, room and capacity carry over, enrolment and
     * timetable start empty and the copy lands in draft.
     */
    public function duplicateCourse(CourseSection $section, string $sectionLabel): CourseSection
    {
        return DB::transaction(function () use ($section, $sectionLabel): CourseSection {
            return $this->writeCourse->handle([
                'tenant_id' => $section->tenant_id,
                'subject_id' => $section->subject_id,
                'academic_year_id' => $section->academic_year_id,
                'campus_id' => $section->campus_id,
                'teacher_id' => $section->teacher_id,
                'grade_label' => $section->grade_label,
                'section_label' => $sectionLabel,
                'room' => $section->room,
                'capacity' => $section->capacity,
                'status' => CourseStatus::Draft->value,
            ]);
        });
    }

    /**
     * Publishing opens enrolment, so the section must be teachable first.
     *
     * @throws ValidationException
     */
    public function assertPublishable(CourseSection $section, CourseStatus $target): void
    {
        if ($target !== CourseStatus::Published) {
            return;
        }

        if (empty($section->teacher_id)) {
            throw ValidationException::withMessages([
                'status' => 'Assign a teacher before publishing.',
            ]);
        }

        $slots = $section->timetable_slots_count ?? $section->timetableSlots()->count();

        if ((int) $slots === 0) {
            throw ValidationException::withMessages([
                'status' => 'Schedule at least one timetable slot before publishing.',
            ]);
        }
    }
}
