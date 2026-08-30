<?php

declare(strict_types=1);

/**
 * Academics capability — configuration.
 *
 * Permission keys extend the Identity catalog (registered via
 * `config/identity.php::registered_capabilities`).
 */
return [
    'permissions' => [
        ['key' => 'academics.subjects.read',    'domain' => 'academics', 'label' => 'View subjects',    'description' => 'See the subject catalog.'],
        ['key' => 'academics.subjects.write',   'domain' => 'academics', 'label' => 'Manage subjects',  'description' => 'Create and edit subject catalog entries.'],
        ['key' => 'academics.courses.read',     'domain' => 'academics', 'label' => 'View course sections',   'description' => 'See taught classes and rosters.'],
        ['key' => 'academics.courses.write',    'domain' => 'academics', 'label' => 'Manage course sections', 'description' => 'Create and edit course sections, enroll students.'],
        ['key' => 'academics.timetable.read',   'domain' => 'academics', 'label' => 'View timetable',   'description' => 'See scheduled periods across the week.'],
        ['key' => 'academics.timetable.write',  'domain' => 'academics', 'label' => 'Manage timetable', 'description' => 'Schedule and remove timetable slots.'],
        ['key' => 'academics.gradebook.read',   'domain' => 'academics', 'label' => 'View gradebook',   'description' => 'See continuous assessment and exam scores.'],
        ['key' => 'academics.gradebook.write',  'domain' => 'academics', 'label' => 'Record grades',    'description' => 'Enter or revise gradebook entries.'],
    ],

    /**
     * Assessment weighting used to derive `total` and `band` on a
     * GradebookEntry. Continuous assessment + exam should sum to `max`.
     */
    'gradebook' => [
        'continuous_assessment_max' => 40,
        'exam_max' => 60,
        'total_max' => 100,
    ],

    /**
     * Default period grid used when a slot is created without explicit
     * start/end times. Mirrors the mock timetable used by the SPA.
     */
    'timetable' => [
        'day_start' => '08:00',   // HH:mm
        'period_minutes' => 40,
        'period_gap_minutes' => 5,
    ],
];
