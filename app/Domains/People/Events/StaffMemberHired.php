<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\StaffMember;
use App\Support\Events\BusinessEvent;

final class StaffMemberHired extends BusinessEvent
{
    public function __construct(public readonly StaffMember $staff)
    {
        parent::__construct($staff->tenant_id);
    }

    public function name(): string
    {
        return 'people.staff.hired';
    }

    public function payload(): array
    {
        return [
            'staff_id' => $this->staff->id,
            'staff_number' => $this->staff->staff_number,
            'campus_id' => $this->staff->campus_id,
            'category' => $this->staff->category->value,
        ];
    }
}
