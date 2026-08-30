<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\UserReactivated;
use App\Domains\Identity\Events\UserSuspended;
use App\Enums\UserStatus;
use App\Models\User;

final class SetUserStatus
{
    public function suspend(User $user, string $tenantId, string $actorId): User
    {
        $user->update(['status' => UserStatus::Suspended]);
        UserSuspended::dispatch($user, $tenantId, $actorId);

        return $user;
    }

    public function reactivate(User $user, string $tenantId, string $actorId): User
    {
        $user->update(['status' => UserStatus::Active]);
        UserReactivated::dispatch($user, $tenantId, $actorId);

        return $user;
    }
}
