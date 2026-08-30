<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Assessments;

use App\Http\Resources\Api\V1\CapabilityResource;
use Illuminate\Http\Request;

/**
 * Wraps the array shape produced by BuildTermReportCards so it flows
 * through the standard Resource envelope. Mirrors
 * src/contracts/assessments.ts::StudentReportCard.
 */
final class StudentReportCardResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'student_id' => (string) ($this['student_id'] ?? ''),
            'student_name' => (string) ($this['student_name'] ?? ''),
            'student_initials' => (string) ($this['student_initials'] ?? ''),
            'grade_label' => (string) ($this['grade_label'] ?? ''),
            'term_id' => (string) ($this['term_id'] ?? ''),
            'term_name' => (string) ($this['term_name'] ?? ''),
            'overall_average' => (float) ($this['overall_average'] ?? 0),
            'overall_band' => (string) ($this['overall_band'] ?? 'F'),
            'lines' => array_map(fn ($l) => [
                'course_section_id' => (string) ($l['course_section_id'] ?? ''),
                'subject_code' => (string) ($l['subject_code'] ?? ''),
                'subject_name' => (string) ($l['subject_name'] ?? ''),
                'average' => (float) ($l['average'] ?? 0),
                'best_band' => (string) ($l['best_band'] ?? 'F'),
                'exams_count' => (int) ($l['exams_count'] ?? 0),
            ], (array) ($this['lines'] ?? [])),
        ];
    }
}
