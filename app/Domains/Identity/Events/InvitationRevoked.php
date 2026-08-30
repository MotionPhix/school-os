<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Models\Invitation;
use App\Support\Events\BusinessEvent;

final class InvitationRevoked extends BusinessEvent
{
    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $revokedByUserId,
    ) {
        parent::__construct($invitation->tenant_id);
    }

    public function name(): string
    {
        return 'identity.invitation.revoked';
    }

    public function payload(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'revoked_by' => $this->revokedByUserId,
        ];
    }
}
