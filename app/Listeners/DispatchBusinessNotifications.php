<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Notifications\Recipients\PermissionRecipients;
use App\Notifications\Recipients\ResolvesNotificationRecipients;
use App\Notifications\Recipients\TenantMembersRecipients;
use App\Notifications\SchoolNotification;
use App\Support\Events\BusinessEvent;
use Illuminate\Support\Collection;

/**
 * Wildcard listener (registered via the universal '*' event, like
 * RecordBusinessEvent) that turns business events into notifications
 * according to config/notifications.php policies.
 *
 * `recipients` accepts: 'tenant_members', 'permission:<key>', a resolver
 * class name (ResolvesNotificationRecipients), or an array of those —
 * recipients are merged and de-duplicated. Every recipient is
 * preference-gated by the notification's via() implementation.
 */
final class DispatchBusinessNotifications
{
    public function handle(BusinessEvent $event): void
    {
        /** @var array<class-string<BusinessEvent>, array{notification: class-string<SchoolNotification>, recipients: string|class-string<ResolvesNotificationRecipients>|array<int, string|class-string<ResolvesNotificationRecipients>>}> $policies */
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

    /**
     * @param  string|array<int, string>  $strategies
     * @return Collection<int, User>
     */
    private function recipients(BusinessEvent $event, string|array $strategies): Collection
    {
        $strategies = is_array($strategies) ? $strategies : [$strategies];

        $users = [];
        foreach ($strategies as $strategy) {
            foreach ($this->resolveStrategy($strategy)->resolve($event) as $user) {
                $users[$user->id] = $user;
            }
        }

        return collect(array_values($users));
    }

    private function resolveStrategy(string $strategy): ResolvesNotificationRecipients
    {
        if ($strategy === 'tenant_members') {
            return new TenantMembersRecipients;
        }

        if (str_starts_with($strategy, 'permission:')) {
            return new PermissionRecipients(substr($strategy, 11));
        }

        /** @var class-string<ResolvesNotificationRecipients> $resolverClass */
        $resolverClass = $strategy;

        $resolver = app($resolverClass);
        \assert($resolver instanceof ResolvesNotificationRecipients);

        return $resolver;
    }
}
