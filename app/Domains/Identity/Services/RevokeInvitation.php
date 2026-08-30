<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\InvitationRevoked;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RevokeInvitation
{
    public function handle(Invitation $invitation, string $actorId): Invitation
    {
        if (! $invitation->status->isOpen()) {
            throw new HttpException(409, 'Invitation is not pending.');
        }

        $invitation->update([
            'status' => InvitationStatus::Revoked,
            'revoked_at' => now(),
        ]);

        InvitationRevoked::dispatch($invitation, $actorId);

        return $invitation;
    }
}
