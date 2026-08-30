<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Enums\StaffStatus;
use App\Models\StaffMember;
use App\Support\Events\BusinessEvent;

final class StaffMemberStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly StaffMember $staff,
        public readonly StaffStatus $from,
        public readonly StaffStatus $to,
    ) {
        parent::__construct($staff->tenant_id);
    }

    public function name(): string
    {
        return 'people.staff.status_changed';
    }

    public function payload(): array
    {
        return [
            'staff_id' => $this->staff->id,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
