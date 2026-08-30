<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Marker for the trash-restore gate — authorize('restore', TrashResource::class)
 * checks `platform.trash.restore` regardless of which resource is being
 * restored (the tenant-scoped target is resolved by TrashController).
 */
final class TrashResource {}
