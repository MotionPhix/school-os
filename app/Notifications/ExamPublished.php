<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Assessments\Events\ExamPublished as ExamPublishedEvent;
use App\Models\CourseSection;

/** In-app delivery for ExamPublished (see config/notifications.php). */
final class ExamPublished extends SchoolNotification
{
    public function __construct(private readonly ExamPublishedEvent $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $exam = $this->event->exam;
        $section = CourseSection::query()->find($exam->course_section_id);

        return [
            'kind' => 'results',
            'title' => "Results published: {$exam->paper_title}",
            'body' => (string) ($section->section_label ?? ''),
            'href' => "/assessments/exams/{$exam->id}",
        ];
    }
}
