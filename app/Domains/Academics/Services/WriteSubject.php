<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Events\SubjectCreated;
use App\Domains\Academics\Events\SubjectUpdated;
use App\Models\Subject;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WriteSubject
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?Subject $existing = null): Subject
    {
        return DB::transaction(function () use ($data, $existing): Subject {
            $creating = $existing === null;
            $subject = $existing ?? new Subject;

            if ($creating) {
                $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();
            }

            if (isset($data['code'])) {
                $data['code'] = mb_strtoupper((string) $data['code']);

                $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->id();
                $query = Subject::query()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $data['code']);

                if ($existing !== null) {
                    $query->whereKeyNot($existing->id);
                }

                if ($query->exists()) {
                    throw ValidationException::withMessages([
                        'code' => 'A subject with this code already exists.',
                    ]);
                }
            }

            $subject->fill($data);
            $subject->save();
            $subject->refresh();

            $creating
                ? SubjectCreated::dispatch($subject)
                : SubjectUpdated::dispatch($subject);

            return $subject;
        });
    }
}
