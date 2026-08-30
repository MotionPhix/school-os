<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

final class InvitationPolicy extends AbstractIdentityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'identity.invitations.read');
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $this->has($user, 'identity.invitations.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'identity.invitations.write');
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $this->has($user, 'identity.invitations.write');
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->has($user, 'identity.invitations.write');
    }
}
