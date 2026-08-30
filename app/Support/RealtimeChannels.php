<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;

/**
 * Single source of truth for realtime channel definitions. routes/channels.php
 * registers them on the runtime broadcaster; tests register the same
 * closures on the recording broadcaster so authorization semantics are
 * exercised against the real callbacks.
 */
final class RealtimeChannels
{
    /** @return array<string, Closure> */
    public static function definitions(): array
    {
        return [
            'users.{id}' => static fn (User $user, string $id): bool => $user->id === $id,
            'tenant.{id}' => static fn (User $user, string $id): bool => $user->memberships()
                ->where('tenants.id', $id)
                ->exists(),
        ];
    }
}
