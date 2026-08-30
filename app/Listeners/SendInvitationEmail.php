<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Identity\Events\UserInvited;
use App\Notifications\InvitationIssued;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Delivers the invitation email. Failures are logged, never fatal — the
 * API still returns the raw token so the caller can fall back to manual
 * delivery if the mail transport is not configured yet.
 *
 * Register in AppServiceProvider (or EventServiceProvider):
 *   Event::listen(UserInvited::class, SendInvitationEmail::class);
 */
final class SendInvitationEmail
{
    public function handle(UserInvited $event): void
    {
        try {
            $event->invitation->loadMissing('tenant');

            Notification::route('mail', $event->invitation->email)
                ->notify(new InvitationIssued($event->invitation, $event->rawToken));
        } catch (Throwable $e) {
            Log::warning('Invitation email delivery failed', [
                'invitation_id' => $event->invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
