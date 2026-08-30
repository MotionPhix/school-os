<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Domains\Admissions\Events\ApplicationCreated;
use App\Domains\Admissions\Events\ApplicationUpdated;
use App\Domains\Admissions\Support\ApplicationReference;
use App\Domains\People\Support\AvatarInitials;
use App\Enums\PipelineStage;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\ApplicationStageEvent;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Create or update an Admissions Application.
 *
 * On create: derives `avatar_initials`, mints the human reference,
 * defaults `stage` to Enquiry and `submitted_at` to now, and appends
 * the initial timeline entry (null -> Enquiry).
 */
final class WriteApplication
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?Application $existing = null, ?User $actor = null): Application
    {
        return DB::transaction(function () use ($data, $existing, $actor): Application {
            $creating = $existing === null;
            $application = $existing ?? new Application;

            if (isset($data['applicant_full_name'])) {
                $data['avatar_initials'] = AvatarInitials::from((string) $data['applicant_full_name']);
            }

            if ($creating) {
                $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->id();
                $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
                $data['tenant_id'] = $tenantId;
                $data['reference'] = $data['reference'] ?? ApplicationReference::generate($tenantId, $year);
                $data['stage'] = $data['stage'] ?? PipelineStage::Enquiry->value;
                $data['submitted_at'] = $data['submitted_at'] ?? now();
            }

            $application->fill($data);
            $application->save();
            $application->refresh();

            if ($creating) {
                $timeline = ApplicationStageEvent::create([
                    'tenant_id' => $application->tenant_id,
                    'application_id' => $application->id,
                    'from_stage' => null,
                    'to_stage' => $application->stage->value,
                    'note' => $data['source_note'] ?? null,
                    'actor_name' => $actor?->name ?? 'System',
                    'actor_id' => $actor?->id,
                    'occurred_at' => now(),
                ]);
                unset($timeline);

                ApplicationCreated::dispatch($application);
            } else {
                ApplicationUpdated::dispatch($application);
            }

            return $application;
        });
    }
}
