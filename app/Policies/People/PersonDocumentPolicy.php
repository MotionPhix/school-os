<?php

declare(strict_types=1);

namespace App\Policies\People;

use App\Models\PersonDocument;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

/**
 * Policy for profile media (avatars + documents).
 *
 * Reads track the parent-subject read permission (student/guardian/staff).
 * Writes require `people.documents.write` plus the parent-subject write
 * permission — someone who cannot edit a student cannot attach files
 * to that student either.
 */
final class PersonDocumentPolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'people.documents.read');
    }

    public function view(User $user, PersonDocument $document): bool
    {
        return $this->has($user, 'people.documents.read')
            && $this->has($user, $this->readKeyFor($document->subject_type));
    }

    public function create(User $user, string $subjectType): bool
    {
        return $this->has($user, 'people.documents.write')
            && $this->has($user, $this->writeKeyFor($subjectType));
    }

    public function delete(User $user, PersonDocument $document): bool
    {
        return $this->has($user, 'people.documents.write')
            && $this->has($user, $this->writeKeyFor($document->subject_type));
    }

    private function readKeyFor(string $subject): string
    {
        return match ($subject) {
            'students' => 'people.students.read',
            'guardians' => 'people.guardians.read',
            'staff' => 'people.staff.read',
            default => 'people.documents.read',
        };
    }

    private function writeKeyFor(string $subject): string
    {
        return match ($subject) {
            'students' => 'people.students.write',
            'guardians' => 'people.guardians.write',
            'staff' => 'people.staff.write',
            default => 'people.documents.write',
        };
    }
}
