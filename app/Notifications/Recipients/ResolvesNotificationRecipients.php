<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Models\User;
use App\Support\Events\BusinessEvent;

/**
 * Extensible recipient resolution for notification policies
 * (config/notifications.php). A policy may reference a resolver class by
 * name — it is resolved from the container per event.
 */
interface ResolvesNotificationRecipients
{
    /** @return iterable<int, User> */
    public function resolve(BusinessEvent $event): iterable;
}
