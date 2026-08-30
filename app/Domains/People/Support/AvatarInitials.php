<?php

declare(strict_types=1);

namespace App\Domains\People\Support;

use Illuminate\Support\Str;

/**
 * Deterministic derivation of initials from a full name.
 *
 * Used on write paths for Student, Guardian, StaffMember so we never
 * store a mismatched avatar_initials value — the field remains
 * denormalised on the DB (useful for lightweight list rendering)
 * but the source of truth is always `full_name`.
 */
final class AvatarInitials
{
    public static function from(string $fullName): string
    {
        $parts = preg_split('/\s+/', mb_trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

        if ($parts === []) {
            return '?';
        }
        if (count($parts) === 1) {
            return Str::upper(Str::substr($parts[0], 0, 2));
        }

        $first = Str::upper(Str::substr($parts[0], 0, 1));
        $last = Str::upper(Str::substr($parts[count($parts) - 1], 0, 1));

        return $first.$last;
    }
}
