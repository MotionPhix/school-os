<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Enums\GuardianStatus;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Models\Campus;
use App\Models\Guardian;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Bulk operations for People tables.
 *
 * Each row is applied through the ordinary single-record services so business
 * events keep firing; rows that violate a guard are skipped with a reason
 * instead of failing the whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkPeopleAction
{
    public function __construct(
        private readonly WriteStudent $writeStudent,
        private readonly WriteStaffMember $writeStaff,
        private readonly IssuePortalAccess $portal,
    ) {}

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function studentStatus(array $ids, StudentStatus $status): array
    {
        $students = $this->fetch(Student::query()->whereIn('id', $ids)->get(), 'students');
        $affected = 0;

        // TODO(Finance): block withdrawal while the student still carries an
        // outstanding receivable once the Finance balance reader is exposed.
        foreach ($students as $student) {
            $this->writeStudent->setStatus($student, $status);
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => []];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function transferStudents(array $ids, Campus $campus): array
    {
        $students = $this->fetch(Student::query()->whereIn('id', $ids)->get(), 'students');
        $skipped = [];
        $affected = 0;

        foreach ($students as $student) {
            if ($student->campus_id === $campus->id) {
                continue;
            }

            if (in_array($student->status, [StudentStatus::Graduated, StudentStatus::Withdrawn], true)) {
                $skipped[] = "{$student->full_name}: only an active record can be transferred.";

                continue;
            }

            $this->writeStudent->handle(['campus_id' => $campus->id], $student);
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function guardianPortalStatus(array $ids, GuardianStatus $status): array
    {
        $guardians = $this->fetch(Guardian::query()->whereIn('id', $ids)->get(), 'guardians');
        $skipped = [];
        $affected = 0;

        foreach ($guardians as $guardian) {
            try {
                $this->portal->setGuardianPortalStatus($guardian, $status);
                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$guardian->full_name}: ".$this->firstMessage($e);
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function resendGuardianInvites(array $ids, User $actor): array
    {
        $guardians = $this->fetch(Guardian::query()->whereIn('id', $ids)->get(), 'guardians');
        $skipped = [];
        $affected = 0;

        foreach ($guardians as $guardian) {
            try {
                $this->portal->inviteGuardian($guardian, $actor);
                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$guardian->full_name}: ".$this->firstMessage($e);
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function staffStatus(array $ids, StaffStatus $status): array
    {
        $members = $this->fetch(StaffMember::query()->whereIn('id', $ids)->get(), 'staff');
        $affected = 0;

        foreach ($members as $member) {
            $this->writeStaff->setStatus($member, $status);
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => []];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function issueStaffLogins(array $ids, User $actor): array
    {
        $members = $this->fetch(StaffMember::query()->whereIn('id', $ids)->get(), 'staff');
        $skipped = [];
        $affected = 0;

        foreach ($members as $member) {
            try {
                $this->portal->issueStaffLogin($member, $actor);
                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$member->full_name}: ".$this->firstMessage($e);
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @template TModel
     *
     * @param  Collection<int,TModel>  $rows
     * @return Collection<int,TModel>
     */
    private function fetch(Collection $rows, string $label): Collection
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['ids' => "No matching {$label}."]);
        }

        return $rows;
    }

    private function firstMessage(ValidationException $e): string
    {
        $first = collect($e->errors())->flatten()->first();

        return is_string($first) ? $first : 'Rejected.';
    }
}
