<?php

declare(strict_types=1);

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Events\MessageThreadOpened;
use App\Enums\MessageThreadStatus;
use App\Enums\ThreadParticipantRole;
use App\Models\MessageThread;
use App\Models\Student;
use App\Models\ThreadParticipant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Open a new message thread with a snapshotted participant list.
 *
 * Participants can arrive without `user_id` (e.g. a guardian who
 * hasn't signed up to the portal yet). Name + role + initials are
 * always required so the thread stays legible.
 */
final class OpenMessageThread
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param array{
     *   subject:string,
     *   student_id?:string|null,
     *   participants:list<array{user_id?:string|null,name:string,role:string,avatar_initials:string}>,
     * } $data
     */
    public function handle(array $data): MessageThread
    {
        return DB::transaction(function () use ($data) {
            $studentName = null;
            if (! empty($data['student_id'])) {
                $studentName = Student::query()
                    ->whereKey($data['student_id'])
                    ->value('full_name');
            }

            $thread = new MessageThread;
            $thread->fill([
                'tenant_id' => $this->tenant->id(),
                'subject' => $data['subject'],
                'status' => MessageThreadStatus::Open->value,
                'student_id' => $data['student_id'] ?? null,
                'student_name' => $studentName,
                'last_message_preview' => '',
                'last_message_at' => now(),
                'unread_count' => 0,
            ]);
            $thread->save();

            foreach ($data['participants'] as $p) {
                $part = new ThreadParticipant;
                $part->fill([
                    'tenant_id' => $this->tenant->id(),
                    'thread_id' => $thread->id,
                    'user_id' => $p['user_id'] ?? null,
                    'name' => $p['name'],
                    'role' => ThreadParticipantRole::from($p['role'])->value,
                    'avatar_initials' => $p['avatar_initials'],
                ]);
                $part->save();
            }

            MessageThreadOpened::dispatch($thread);

            return $thread->refresh();
        });
    }
}
