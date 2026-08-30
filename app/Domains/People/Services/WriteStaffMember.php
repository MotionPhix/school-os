<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Domains\People\Events\StaffMemberHired;
use App\Domains\People\Events\StaffMemberStatusChanged;
use App\Domains\People\Events\StaffMemberUpdated;
use App\Domains\People\Support\AvatarInitials;
use App\Enums\StaffStatus;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;

final class WriteStaffMember
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?StaffMember $existing = null): StaffMember
    {
        return DB::transaction(function () use ($data, $existing): StaffMember {
            $creating = $existing === null;
            $staff = $existing ?? new StaffMember;
            $priorStatus = $creating ? null : $staff->status;

            if (isset($data['full_name'])) {
                $data['avatar_initials'] = AvatarInitials::from((string) $data['full_name']);
            }
            if (! array_key_exists('subjects_taught', $data) && $creating) {
                $data['subjects_taught'] = [];
            }

            $staff->fill($data);
            $staff->save();
            $staff->refresh();

            if ($creating) {
                StaffMemberHired::dispatch($staff);
            } else {
                StaffMemberUpdated::dispatch($staff);
                if ($priorStatus !== null && $priorStatus !== $staff->status) {
                    StaffMemberStatusChanged::dispatch($staff, $priorStatus, $staff->status);
                }
            }

            return $staff;
        });
    }

    public function setStatus(StaffMember $staff, StaffStatus $status): StaffMember
    {
        $prior = $staff->status;
        if ($prior === $status) {
            return $staff;
        }

        return DB::transaction(function () use ($staff, $status, $prior): StaffMember {
            $staff->status = $status;
            $staff->save();
            $staff->refresh();

            StaffMemberStatusChanged::dispatch($staff, $prior, $status);

            return $staff;
        });
    }
}
