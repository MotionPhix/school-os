<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\Invitation;
use App\Support\Events\BusinessEvent;

final class UserInvited extends BusinessEvent
{
    public function __construct(
        public readonly Invitation $invitation,
        /** Raw token — emitted once, never persisted. */
        public readonly string $rawToken,
    ) {
        parent::__construct($invitation->tenant_id);
    }

    public function name(): string
    {
        return 'identity.user.invited';
    }

    public function payload(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'role_ids' => $this->invitation->role_ids,
            'expires_at' => $this->invitation->expires_at->toIso8601String(),
        ];
    }
}
