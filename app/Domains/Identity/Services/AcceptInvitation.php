<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\InvitationAccepted;
use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AcceptInvitation
{
    /**
     * @param  array{name:string, password:string}  $profile
     */
    public function handle(string $rawToken, array $profile): User
    {
        return DB::transaction(function () use ($rawToken, $profile): User {
            $invitation = Invitation::query()
                ->withoutGlobalScopes()
                ->where('token_hash', hash('sha256', $rawToken))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isRedeemable()) {
                throw new HttpException(410, 'Invitation is no longer valid.');
            }

            $user = User::query()
                ->where('email', $invitation->email)
                ->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $profile['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($profile['password']),
                    'status' => UserStatus::Active,
                    'active_tenant_id' => $invitation->tenant_id,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } elseif ($user->active_tenant_id === null) {
                $user->update(['active_tenant_id' => $invitation->tenant_id]);
            }

            TenantMembership::updateOrCreate(
                ['user_id' => $user->id, 'tenant_id' => $invitation->tenant_id],
                ['role_ids' => $invitation->role_ids, 'joined_at' => now()],
            );

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            InvitationAccepted::dispatch($invitation, $user);

            return $user;
        });
    }
}
