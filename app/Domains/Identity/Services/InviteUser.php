<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\UserInvited;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InviteUser
{
    /**
     * Issues an invitation for `email` to join the current tenant with
     * the given role ids. Returns the persisted Invitation plus the
     * one-time raw token (caller emails it to the invitee).
     *
     * @param  list<string>  $roleIds
     * @return array{invitation:Invitation, raw_token:string}
     */
    public function handle(string $tenantId, string $email, array $roleIds, User $invitedBy): array
    {
        return DB::transaction(function () use ($tenantId, $email, $roleIds, $invitedBy): array {
            $email = mb_strtolower(mb_trim($email));

            // Revoke any prior pending invite for the same email+tenant so
            // there is always at most one live invitation.
            Invitation::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->where('status', InvitationStatus::Pending->value)
                ->update([
                    'status' => InvitationStatus::Revoked->value,
                    'revoked_at' => now(),
                ]);

            $raw = Str::random(48);

            $invitation = Invitation::create([
                'tenant_id' => $tenantId,
                'email' => $email,
                'role_ids' => array_values(array_unique($roleIds)),
                'token_hash' => hash('sha256', $raw),
                'status' => InvitationStatus::Pending,
                'invited_by_id' => $invitedBy->id,
                'expires_at' => now()->addDays((int) config('identity.invitation.ttl_days', 14)),
            ]);

            UserInvited::dispatch($invitation, $raw);

            return ['invitation' => $invitation, 'raw_token' => $raw];
        });
    }
}
