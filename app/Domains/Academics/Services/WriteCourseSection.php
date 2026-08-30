<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Events\CourseSectionCreated;
use App\Domains\Academics\Events\CourseSectionStatusChanged;
use App\Domains\Academics\Events\CourseSectionUpdated;
use App\Enums\CourseStatus;
use App\Models\CourseSection;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WriteCourseSection
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?CourseSection $existing = null): CourseSection
    {
        return DB::transaction(function () use ($data, $existing): CourseSection {
            $creating = $existing === null;
            $section = $existing ?? new CourseSection;
            $previousStatus = $existing?->status;

            if ($creating) {
                $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();
                $data['status'] = $data['status'] ?? CourseStatus::Draft->value;
            }

            // Friendly duplicate check for the identity unique constraint
            // (tenant, academic_year, subject, section_label).
            if (isset($data['academic_year_id'], $data['subject_id'], $data['section_label'])) {
                $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->id();
                $query = CourseSection::query()
                    ->where('tenant_id', $tenantId)
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('subject_id', $data['subject_id'])
                    ->where('section_label', $data['section_label']);

                if ($existing !== null) {
                    $query->whereKeyNot($existing->id);
                }

                if ($query->exists()) {
                    throw ValidationException::withMessages([
                        'section_label' => 'A section with this label already exists for this subject and year.',
                    ]);
                }
            }

            $section->fill($data);
            $section->save();
            $section->refresh();

            if ($creating) {
                CourseSectionCreated::dispatch($section);
            } else {
                CourseSectionUpdated::dispatch($section);
                if ($previousStatus !== null && $previousStatus !== $section->status) {
                    CourseSectionStatusChanged::dispatch($section, $previousStatus);
                }
            }

            return $section;
        });
    }
}
