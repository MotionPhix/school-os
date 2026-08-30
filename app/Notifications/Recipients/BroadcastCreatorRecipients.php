<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Domains\Communications\Events\BroadcastCompleted;
use App\Models\User;
use App\Support\Events\BusinessEvent;

/**
 * The user who created the completed broadcast (system broadcasts
 * without a creator are skipped).
 */
final class BroadcastCreatorRecipients implements ResolvesNotificationRecipients
{
    public function resolve(BusinessEvent $event): iterable
    {
        if (! $event instanceof BroadcastCompleted) {
            return [];
        }

        $creatorId = $event->broadcast->created_by;
        if ($creatorId === null) {
            return [];
        }

        $creator = User::query()->find($creatorId);

        return $creator === null ? [] : [$creator];
    }
}
