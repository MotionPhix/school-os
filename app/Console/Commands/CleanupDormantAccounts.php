<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Housekeeping for stale self-serve signups and invitations.
 *
 * Three passes, each idempotent and safe to re-run:
 *   1. Pending invitations past expires_at  → status = expired.
 *   2. Invitations expired longer than --purge-after days → deleted.
 *   3. Users still `invited` with no verified email and no activity for
 *      --dormant-after days → status = deactivated (never hard-deleted,
 *      so audit history and any accidental references stay intact).
 *
 * Schedule daily (see routes/console.php):
 *   Schedule::command('schoolos:cleanup-accounts')->dailyAt('02:15');
 */
final class CleanupDormantAccounts extends Command
{
    protected $signature = 'schoolos:cleanup-accounts
        {--dormant-after=30 : Days a pending account may stay untouched}
        {--purge-after=90 : Days after expiry before an invitation row is deleted}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Expire stale invitations and deactivate dormant pending accounts.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dormantAfter = (int) $this->option('dormant-after');
        $purgeAfter = (int) $this->option('purge-after');

        $now = Carbon::now();

        // --- 1. Expire overdue pending invitations ---------------------------
        $overdue = Invitation::query()
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '<', $now)
            ->get();

        foreach ($overdue as $invitation) {
            if (! $dryRun) {
                $invitation->update(['status' => InvitationStatus::Expired]);
                $this->record($invitation->tenant_id, 'identity.invitation.expired', [
                    'subject_type' => 'invitation',
                    'subject_id' => $invitation->id,
                    'subject_label' => $invitation->email,
                    'summary' => "Invitation for {$invitation->email} expired",
                ]);
            }
        }
        $this->info("Expired invitations: {$overdue->count()}");

        // --- 2. Purge long-dead invitation rows ------------------------------
        $purgeQuery = Invitation::query()
            ->whereIn('status', [
                InvitationStatus::Expired->value,
                InvitationStatus::Revoked->value,
            ])
            ->where('expires_at', '<', $now->copy()->subDays($purgeAfter));

        $purgeCount = (clone $purgeQuery)->count();
        if (! $dryRun) {
            $purgeQuery->delete();
        }
        $this->info("Purged invitation rows: {$purgeCount}");

        // --- 3. Deactivate dormant pending accounts --------------------------
        $cutoff = $now->copy()->subDays($dormantAfter);

        $dormant = User::query()
            ->where('status', UserStatus::Invited->value)
            ->whereNull('email_verified_at')
            ->whereNull('last_active_at')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($dormant as $user) {
            if ($dryRun) {
                continue;
            }

            $user->update(['status' => UserStatus::Deactivated]);

            $tenantId = $user->active_tenant_id
                ?? $user->memberships()->first()?->id;

            if ($tenantId !== null) {
                $this->record($tenantId, 'identity.user.deactivated', [
                    'subject_type' => 'user',
                    'subject_id' => $user->id,
                    'subject_label' => $user->name,
                    'summary' => "Deactivated dormant pending account {$user->email}",
                    'metadata' => ['reason' => 'dormant', 'dormant_after_days' => $dormantAfter],
                ]);
            }
        }
        $this->info("Deactivated dormant accounts: {$dormant->count()}");

        if ($dryRun) {
            $this->warn('Dry run — no changes were written.');
        }

        return self::SUCCESS;
    }

    /** @param array<string,mixed> $attributes */
    private function record(string $tenantId, string $name, array $attributes): void
    {
        AuditEvent::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'actor_id' => null,
            'actor_name' => 'System (scheduler)',
            'metadata' => $attributes['metadata'] ?? [],
            'occurred_at' => now(),
        ] + $attributes);
    }
}
