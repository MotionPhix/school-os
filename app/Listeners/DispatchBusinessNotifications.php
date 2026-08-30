<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Notifications\SchoolNotification;
use App\Support\Events\BusinessEvent;

/**
 * Wildcard listener (registered via the universal '*' event, like
 * RecordBusinessEvent) that turns business events into notifications
 * according to config/notifications.php policies.
 *
 * Recipients are resolved per policy; every recipient is preference-gated
 * by the notification's via() implementation.
 */
final class DispatchBusinessNotifications
{
    public function handle(BusinessEvent $event): void
    {
        /** @var array<class-string<BusinessEvent>, array{notification: class-string<SchoolNotification>, recipients: string}> $policies */
        $policies = config('notifications.policies', []);

        foreach ($policies as $eventClass => $policy) {
            if (! $event instanceof $eventClass) {
                continue;
            }

            $notificationClass = $policy['notification'];
            $recipients = $this->recipients($event, $policy['recipients']);

            foreach ($recipients as $recipient) {
                $recipient->notify(new $notificationClass($event));
            }
        }
    }

    /** @return iterable<User> */
    private function recipients(BusinessEvent $event, string $strategy): iterable
    {
        $users = User::query()
            ->whereHas('memberships', fn ($query) => $query->where('tenants.id', $event->tenantId))
            ->get();

        if (str_starts_with($strategy, 'permission:')) {
            $key = substr($strategy, 11);
            $users = $users->filter(fn (User $user): bool => $user->hasPermission($key, $event->tenantId));
        }

        return $users;
    }
}
