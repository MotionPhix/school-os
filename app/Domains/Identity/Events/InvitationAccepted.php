<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Events\BusinessEvent;

final class InvitationAccepted extends BusinessEvent
{
    public function __construct(
        public readonly Invitation $invitation,
        public readonly User $user,
    ) {
        parent::__construct($invitation->tenant_id);
    }

    public function name(): string
    {
        return 'identity.invitation.accepted';
    }

    public function payload(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'role_ids' => $this->invitation->role_ids,
        ];
    }
}
