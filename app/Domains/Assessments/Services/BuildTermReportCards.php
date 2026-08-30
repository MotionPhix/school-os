<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Enums\ExamStatus;
use App\Enums\GradeBand;
use App\Models\ExamResult;
use App\Models\Term;

/**
 * Rolls up published exam results across a term into per-student
 * report cards. Mirrors src/lib/verbs/assessments.ts::reports.term
 * so the API response drops straight into the SPA's
 * StudentReportCard[] shape without post-processing.
 *
 * Grouping strategy:
 *   student -> course_section (subject)
 *     -> line { avg%, best_band, exams_count }
 *   student
 *     -> overall_average = mean(line averages)
 *     -> overall_band    = bandFor(overall_average)
 *
 * Only exams with status = `published` inside the target term contribute.
 */
final class BuildTermReportCards
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Term $term): array
    {
        $rows = ExamResult::query()
            ->join('exams', 'exams.id', '=', 'exam_results.exam_id')
            ->join('exam_periods', 'exam_periods.id', '=', 'exams.period_id')
            ->join('course_sections', 'course_sections.id', '=', 'exams.course_section_id')
            ->join('subjects', 'subjects.id', '=', 'course_sections.subject_id')
            ->join('students', 'students.id', '=', 'exam_results.student_id')
            ->where('exams.status', ExamStatus::Published->value)
            ->where('exam_periods.term_id', $term->id)
            ->whereNotNull('exam_results.score')
            ->get([
                'exam_results.student_id',
                'students.full_name as student_name',
                'students.avatar_initials as student_initials',
                'students.grade_label',
                'exams.course_section_id',
                'subjects.code as subject_code',
                'subjects.name as subject_name',
                'exam_results.score',
                'exams.max_score',
            ]);

        $bucket = [];
        foreach ($rows as $r) {
            $sid = (string) $r->student_id;
            $csid = (string) $r->course_section_id;
            $pct = ((int) $r->max_score) > 0
                ? ((int) $r->score / (int) $r->max_score) * 100
                : 0;

            $bucket[$sid] ??= [
                'student_id' => $sid,
                'student_name' => (string) $r->student_name,
                'student_initials' => (string) $r->student_initials,
                'grade_label' => (string) $r->grade_label,
                'subjects' => [],
            ];
            $bucket[$sid]['subjects'][$csid] ??= [
                'course_section_id' => $csid,
                'subject_code' => (string) $r->subject_code,
                'subject_name' => (string) $r->subject_name,
                'total' => 0.0,
                'count' => 0,
                'best' => 0.0,
            ];
            $entry = &$bucket[$sid]['subjects'][$csid];
            $entry['total'] += $pct;
            $entry['count'] += 1;
            $entry['best'] = max($entry['best'], $pct);
            unset($entry);
        }

        $cards = [];
        foreach ($bucket as $sid => $s) {
            $lines = [];
            $sum = 0.0;
            $subjectCount = 0;
            foreach ($s['subjects'] as $sub) {
                $avg = $sub['count'] ? $sub['total'] / $sub['count'] : 0.0;
                $lines[] = [
                    'course_section_id' => $sub['course_section_id'],
                    'subject_code' => $sub['subject_code'],
                    'subject_name' => $sub['subject_name'],
                    'average' => round($avg * 10) / 10,
                    'best_band' => GradeBand::forTotal((int) round($sub['best']))->value,
                    'exams_count' => $sub['count'],
                ];
                $sum += $avg;
                $subjectCount += 1;
            }
            usort($lines, fn ($a, $b) => strcmp($a['subject_code'], $b['subject_code']));
            $overall = $subjectCount ? $sum / $subjectCount : 0.0;

            $cards[] = [
                'student_id' => $s['student_id'],
                'student_name' => $s['student_name'],
                'student_initials' => $s['student_initials'],
                'grade_label' => $s['grade_label'],
                'term_id' => $term->id,
                'term_name' => $term->name,
                'overall_average' => round($overall * 10) / 10,
                'overall_band' => GradeBand::forTotal((int) round($overall))->value,
                'lines' => $lines,
            ];
        }

        usort($cards, fn ($a, $b) => $b['overall_average'] <=> $a['overall_average']);

        return $cards;
    }
}
