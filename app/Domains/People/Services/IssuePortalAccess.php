<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Domains\Identity\Services\InviteUser;
use App\Domains\People\Events\GuardianUpdated;
use App\Domains\People\Events\StaffMemberUpdated;
use App\Enums\GuardianStatus;
use App\Enums\StaffStatus;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Portal access for People aggregates.
 *
 * Guardians and staff never receive credentials directly from the People
 * capability: an Identity Invitation is issued and the People record simply
 * records the resulting access state. That keeps Identity the single owner of
 * authentication while People stays the owner of the person record.
 */
final class IssuePortalAccess
{
    public function __construct(private readonly InviteUser $inviteUser) {}

    /**
     * Invite (or re-invite) a guardian to the parent portal.
     */
    public function inviteGuardian(Guardian $guardian, User $actor): Guardian
    {
        $email = $guardian->contact_email;

        if ($email === null || $email === '') {
            throw ValidationException::withMessages([
                'contact_email' => 'This guardian has no email address on file.',
            ]);
        }

        if ($guardian->portal_status === GuardianStatus::Active) {
            throw ValidationException::withMessages([
                'portal_status' => "{$guardian->full_name} already has portal access.",
            ]);
        }

        return DB::transaction(function () use ($guardian, $actor, $email): Guardian {
            $roleIds = $this->roleIds((string) config('people.guardian_role_key', 'guardian'));

            $this->inviteUser->handle($guardian->tenant_id, $email, $roleIds, $actor);

            $guardian->portal_status = GuardianStatus::Invited;
            $guardian->save();
            $guardian->refresh();

            GuardianUpdated::dispatch($guardian);

            return $guardian;
        });
    }

    public function setGuardianPortalStatus(Guardian $guardian, GuardianStatus $status): Guardian
    {
        if ($guardian->portal_status === $status) {
            return $guardian;
        }

        if ($status !== GuardianStatus::Inactive && ($guardian->contact_email === null || $guardian->contact_email === '')) {
            throw ValidationException::withMessages([
                'contact_email' => 'Add an email address before enabling portal access.',
            ]);
        }

        return DB::transaction(function () use ($guardian, $status): Guardian {
            $guardian->portal_status = $status;
            $guardian->save();
            $guardian->refresh();

            GuardianUpdated::dispatch($guardian);

            return $guardian;
        });
    }

    /**
     * Issue a staff login by inviting the work email into Identity.
     */
    public function issueStaffLogin(StaffMember $staff, User $actor): StaffMember
    {
        if ($staff->user_id !== null) {
            throw ValidationException::withMessages([
                'user_id' => "{$staff->full_name} already has a login.",
            ]);
        }

        if ($staff->status === StaffStatus::Separated) {
            throw ValidationException::withMessages([
                'status' => 'Separated staff cannot be issued a login.',
            ]);
        }

        $email = $staff->contact_email;

        if ($email === null || $email === '') {
            throw ValidationException::withMessages([
                'contact_email' => 'Add a work email before issuing a login.',
            ]);
        }

        return DB::transaction(function () use ($staff, $actor, $email): StaffMember {
            $existing = User::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('email', mb_strtolower($email))
                ->first();

            if ($existing !== null) {
                $staff->user_id = $existing->id;
            } else {
                $roleIds = $this->roleIds($this->staffRoleKey($staff));
                $this->inviteUser->handle($staff->tenant_id, $email, $roleIds, $actor);
            }

            $staff->save();
            $staff->refresh();

            StaffMemberUpdated::dispatch($staff);

            return $staff;
        });
    }

    public function revokeStaffLogin(StaffMember $staff): StaffMember
    {
        if ($staff->user_id === null) {
            throw ValidationException::withMessages([
                'user_id' => "{$staff->full_name} has no login to revoke.",
            ]);
        }

        return DB::transaction(function () use ($staff): StaffMember {
            $staff->user_id = null;
            $staff->save();
            $staff->refresh();

            StaffMemberUpdated::dispatch($staff);

            return $staff;
        });
    }

    private function staffRoleKey(StaffMember $staff): string
    {
        $map = (array) config('people.staff_role_keys', []);

        return (string) ($map[$staff->category->value] ?? config('people.staff_default_role_key', 'teacher'));
    }

    /** @return list<string> */
    private function roleIds(string $key): array
    {
        $role = Role::query()->where('key', $key)->first();

        return $role === null ? [] : [$role->id];
    }
}
