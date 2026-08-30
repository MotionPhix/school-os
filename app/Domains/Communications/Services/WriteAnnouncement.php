<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\AnnouncementDrafted;
use App\Domains\Communications\Support\AudienceEstimator;
use App\Enums\AnnouncementStatus;
use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Models\Announcement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Draft or schedule an announcement.
 *
 * - A `scheduled_for` value moves it to Scheduled; otherwise it stays
 *   as a Draft the author can continue editing.
 * - `recipient_count` is estimated from the current tenant data via
 *   AudienceEstimator so the UI can show impact before send.
 */
final class WriteAnnouncement
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AudienceEstimator $estimator,
    ) {}

    /**
     * @param array{
     *   title:string,
     *   body:string,
     *   audience:string,
     *   audience_label:string,
     *   channels:list<string>,
     *   scheduled_for?:string|null,
     * } $data
     */
    public function create(array $data, User $actor): Announcement
    {
        $audience = CommunicationAudience::from($data['audience']);
        $channels = array_values(array_unique(array_map(
            fn (string $c) => CommunicationChannel::from($c)->value,
            $data['channels'],
        )));
        $scheduledFor = $data['scheduled_for'] ?? null;

        return DB::transaction(function () use ($data, $audience, $channels, $scheduledFor, $actor) {
            $ann = new Announcement;
            $ann->fill([
                'tenant_id' => $this->tenant->id(),
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $audience->value,
                'audience_label' => $data['audience_label'],
                'channels' => $channels,
                'status' => $scheduledFor
                    ? AnnouncementStatus::Scheduled->value
                    : AnnouncementStatus::Draft->value,
                'author_id' => $actor->id,
                'author_name' => $actor->name,
                'scheduled_for' => $scheduledFor,
                'recipient_count' => $this->estimator->count($audience),
            ]);
            $ann->save();

            AnnouncementDrafted::dispatch($ann);

            return $ann->refresh();
        });
    }

    /**
     * Update an editable (Draft or Scheduled) announcement.
     *
     * @param  array<string,mixed>  $data
     */
    public function update(Announcement $ann, array $data): Announcement
    {
        return DB::transaction(function () use ($ann, $data) {
            if (array_key_exists('audience', $data) && $data['audience'] !== null) {
                $audience = CommunicationAudience::from((string) $data['audience']);
                $ann->audience = $audience;
                $ann->recipient_count = $this->estimator->count($audience);
            }
            if (array_key_exists('channels', $data) && $data['channels'] !== null) {
                $ann->channels = array_values(array_unique(array_map(
                    fn (string $c) => CommunicationChannel::from($c)->value,
                    (array) $data['channels'],
                )));
            }
            foreach (['title', 'body', 'audience_label', 'scheduled_for'] as $field) {
                if (array_key_exists($field, $data)) {
                    $ann->{$field} = $data[$field];
                }
            }

            if ($ann->scheduled_for !== null && $ann->status === AnnouncementStatus::Draft) {
                $ann->status = AnnouncementStatus::Scheduled;
            }
            if ($ann->scheduled_for === null && $ann->status === AnnouncementStatus::Scheduled) {
                $ann->status = AnnouncementStatus::Draft;
            }

            $ann->save();

            return $ann->refresh();
        });
    }
}
