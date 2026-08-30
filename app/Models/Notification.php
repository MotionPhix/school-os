<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Tenant-scoped in-app notification (read model). Rows are written by the
 * TenantDatabaseChannel, which stamps `id` and `tenant_id` — so this
 * model stays free of the tenant/uuid concerns.
 */
final class Notification extends DatabaseNotification
{
    protected $table = 'notifications';
}
