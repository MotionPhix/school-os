<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Domains\People\Events\StudentGuardianLinked;
use App\Domains\People\Events\StudentGuardianUnlinked;
use App\Models\Guardian;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Manage the Student <-> Guardian pivot. Exactly one guardian per
 * student may be marked `is_primary`; promoting a new one demotes any
 * siblings in the same transaction.
 */
final class LinkStudentGuardian
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function link(
        Student $student,
        Guardian $guardian,
        string $relationship,
        bool $isPrimary,
    ): void {
        DB::transaction(function () use ($student, $guardian, $relationship, $isPrimary): void {
            $student->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'tenant_id' => $this->tenants->id(),
                    'relationship' => $relationship,
                    'is_primary' => $isPrimary,
                ],
            ]);

            if ($isPrimary) {
                DB::table('student_guardians')
                    ->where('student_id', $student->id)
                    ->where('guardian_id', '!=', $guardian->id)
                    ->update(['is_primary' => false]);
            }

            StudentGuardianLinked::dispatch($student, $guardian, $relationship, $isPrimary);
        });
    }

    public function unlink(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian): void {
            $student->guardians()->detach($guardian->id);
            StudentGuardianUnlinked::dispatch(
                $student->tenant_id,
                $student->id,
                $guardian->id,
            );
        });
    }
}
