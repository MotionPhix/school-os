<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\BroadcastDrafted;
use App\Domains\Communications\Support\AudienceEstimator;
use App\Enums\BroadcastStatus;
use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Enums\CurrencyCode;
use App\Models\Broadcast;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Draft or update a broadcast. SMS cost is snapshotted at draft time
 * from `config/communications.php::sms_cost_minor_per_recipient` × the
 * estimated audience so the bursar sees expected spend before starting.
 */
final class WriteBroadcast
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AudienceEstimator $estimator,
    ) {}

    /**
     * @param array{
     *   name:string,
     *   channel:string,
     *   audience:string,
     *   audience_label:string,
     *   template_snippet:string,
     *   scheduled_for?:string|null,
     * } $data
     */
    public function create(array $data, User $actor): Broadcast
    {
        $channel = CommunicationChannel::from($data['channel']);
        $audience = CommunicationAudience::from($data['audience']);
        $recipients = $this->estimator->count($audience);

        return DB::transaction(function () use ($data, $channel, $audience, $recipients, $actor) {
            $b = new Broadcast;
            $b->fill([
                'tenant_id' => $this->tenant->id(),
                'name' => $data['name'],
                'channel' => $channel->value,
                'audience' => $audience->value,
                'audience_label' => $data['audience_label'],
                'template_snippet' => $data['template_snippet'],
                'status' => empty($data['scheduled_for'])
                    ? BroadcastStatus::Draft->value
                    : BroadcastStatus::Queued->value,
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'recipient_count' => $recipients,
                'cost_minor' => $channel === CommunicationChannel::Sms
                    ? $recipients * (int) config('communications.sms_cost_minor_per_recipient', 0)
                    : 0,
                'currency' => CurrencyCode::from((string) config('communications.currency', 'MWK'))->value,
                'created_by' => $actor->id,
            ]);
            $b->save();

            BroadcastDrafted::dispatch($b);

            return $b->refresh();
        });
    }
}
