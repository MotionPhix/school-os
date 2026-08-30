<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user, per-notification, per-channel opt-out (default: enabled).
 * Kept intentionally minimal for Phase 1 — the UI to manage these lands
 * with the preferences surface.
 */
final class NotificationPreference extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'notification',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public static function isEnabled(object $user, string $notificationClass, string $channel): bool
    {
        if (! $user instanceof User) {
            return true;
        }

        return (bool) (self::query()
            ->where('user_id', $user->id)
            ->where('notification', $notificationClass)
            ->where('channel', $channel)
            ->value('enabled') ?? true);
    }
}
