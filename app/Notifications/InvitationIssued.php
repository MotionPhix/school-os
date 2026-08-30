<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivery for a freshly issued (or resent) invitation. The raw token is
 * passed in — it is never persisted, only its sha256 hash lives on the row.
 */
final class InvitationIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $rawToken,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant?->name ?? 'SchoolOS';

        $url = mb_rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/verify?token='.$this->rawToken
            .'&email='.urlencode($this->invitation->email);

        return (new MailMessage)
            ->subject("You've been invited to {$tenantName} on SchoolOS")
            ->greeting('Hello,')
            ->line("{$tenantName} has invited you to join their SchoolOS workspace.")
            ->action('Accept invitation', $url)
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.')
            ->line('If you were not expecting this invitation you can safely ignore this email.');
    }
}
