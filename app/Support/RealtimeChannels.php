<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for realtime channel definitions. routes/channels.php
 * registers them on the runtime broadcaster; tests register the same
 * closures on the recording broadcaster so authorization semantics are
 * exercised against the real callbacks.
 *
 * Presence channels (`sessions`, `exams`, `threads`) return the joiner's
 * public payload so the server can track who is in the room; they gate on
 * the same permission keys as the API policies.
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

            // Presence — attendance register co-edit (staff who can write marks).
            'sessions.{id}' => static fn (User $user, string $id): array|false => self::has($user, 'attendance.sessions.write')
                ? self::payload($user)
                : false,

            // Presence — exam marksheet co-edit (staff who can record results).
            'exams.{id}' => static fn (User $user, string $id): array|false => self::has($user, 'assessments.results.write')
                ? self::payload($user)
                : false,

            // Presence — thread conversation viewers (participants only).
            'threads.{id}' => static fn (User $user, string $id): array|false => self::isThreadParticipant($user, $id)
                ? self::payload($user)
                : false,
        ];
    }

    /** @return array{id: string, name: string} */
    private static function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    private static function has(User $user, string $key): bool
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            return false;
        }

        $raw = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->value('role_ids');

        if (! is_string($raw)) {
            return false;
        }

        /** @var array<int, string>|null $roleIds */
        $roleIds = json_decode($raw, true);

        if (! is_array($roleIds) || $roleIds === []) {
            return false;
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->get(['permission_keys'])
            ->flatMap(fn ($role): array => $role->permission_keys)
            ->contains($key);
    }

    private static function isThreadParticipant(User $user, string $threadId): bool
    {
        return DB::table('comm_thread_participants')
            ->where('thread_id', $threadId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
