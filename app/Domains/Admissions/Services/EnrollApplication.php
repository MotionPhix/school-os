<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Domains\Admissions\Events\ApplicationEnrolled;
use App\Domains\People\Services\WriteStudent;
use App\Enums\PipelineStage;
use App\Enums\StudentStatus;
use App\Models\Application;
use App\Models\ApplicationStageEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Terminal Admissions transition: mint a Student aggregate from the
 * accepted Application, stamp `student_id` back onto the Application,
 * and move it to Enrolled.
 *
 * Only Accepted applications may be enrolled; enrolling a second time
 * is a no-op that returns the linked Student.
 */
final class EnrollApplication
{
    public function __construct(private readonly WriteStudent $writeStudent) {}

    public function handle(Application $application, User $actor, ?string $admissionNumber = null): Student
    {
        if ($application->stage === PipelineStage::Enrolled && $application->student_id !== null) {
            return Student::query()->findOrFail($application->student_id);
        }

        if ($application->stage !== PipelineStage::Accepted) {
            throw new HttpException(
                422,
                "Only applications in 'accepted' stage can be enrolled (current: {$application->stage->value}).",
            );
        }

        return DB::transaction(function () use ($application, $actor, $admissionNumber): Student {
            $student = $this->writeStudent->handle([
                'tenant_id' => $application->tenant_id,
                'campus_id' => $application->campus_id,
                'admission_number' => $admissionNumber ?? $this->deriveAdmissionNumber($application),
                'full_name' => $application->applicant_full_name,
                'preferred_name' => $application->applicant_preferred_name,
                'gender' => $application->gender->value,
                'date_of_birth' => $application->date_of_birth->toDateString(),
                'stage' => $application->intended_stage->value,
                'grade_label' => $application->intended_grade_label,
                'status' => StudentStatus::Enrolled->value,
                'enrolled_on' => now()->toDateString(),
            ]);

            $from = $application->stage;
            $application->student_id = $student->id;
            $application->stage = PipelineStage::Enrolled;
            $application->save();
            $application->refresh();

            ApplicationStageEvent::create([
                'tenant_id' => $application->tenant_id,
                'application_id' => $application->id,
                'from_stage' => $from->value,
                'to_stage' => PipelineStage::Enrolled->value,
                'note' => "Converted to student record {$student->admission_number}",
                'actor_name' => $actor->name,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            ApplicationEnrolled::dispatch($application, $student);

            return $student;
        });
    }

    /**
     * Derive a fallback admission number when the caller doesn't supply one.
     * Uses the application reference tail so the two records are visibly
     * linked in ops UIs until an institution-specific numbering policy lands.
     */
    private function deriveAdmissionNumber(Application $application): string
    {
        $tail = preg_replace('/[^A-Za-z0-9]/', '', (string) $application->reference) ?: 'STU';

        return 'STU-'.mb_strtoupper(mb_substr($tail, -8));
    }
}
