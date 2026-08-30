<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\StaffMember;
use App\Support\Events\BusinessEvent;

final class StaffMemberUpdated extends BusinessEvent
{
    public function __construct(public readonly StaffMember $staff)
    {
        parent::__construct($staff->tenant_id);
    }

    public function name(): string
    {
        return 'people.staff.updated';
    }

    public function payload(): array
    {
        return [
            'staff_id' => $this->staff->id,
            'title' => $this->staff->title,
        ];
    }
}
