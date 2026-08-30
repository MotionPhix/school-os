<?php

declare(strict_types=1);

/**
 * Assessments & Exams capability — configuration.
 *
 * Permission keys extend the Identity catalog (registered via
 * `config/identity.php::registered_capabilities`).
 */
return [
    'permissions' => [
        ['key' => 'assessments.periods.read',   'domain' => 'assessments', 'label' => 'View exam periods',   'description' => 'See scheduled exam windows within a term.'],
        ['key' => 'assessments.periods.write',  'domain' => 'assessments', 'label' => 'Manage exam periods', 'description' => 'Create, edit and close exam windows.'],
        ['key' => 'assessments.exams.read',     'domain' => 'assessments', 'label' => 'View exams',          'description' => 'See exam papers and their rosters.'],
        ['key' => 'assessments.exams.write',    'domain' => 'assessments', 'label' => 'Manage exams',        'description' => 'Create, edit and schedule exam papers.'],
        ['key' => 'assessments.results.write',  'domain' => 'assessments', 'label' => 'Record exam results', 'description' => 'Enter or revise per-student scores while an exam is in marking.'],
        ['key' => 'assessments.publish',        'domain' => 'assessments', 'label' => 'Publish exam results', 'description' => 'Flip an exam from marking to published so results roll into report cards.'],
        ['key' => 'assessments.reports.read',   'domain' => 'assessments', 'label' => 'View report cards',   'description' => 'See per-student rollups of published results in a term.'],
    ],

    /**
     * Exam defaults applied when a paper is created without explicit
     * bounds. `max_score` is the top mark; `pass_mark` is the boundary
     * used by SPA at-a-glance colouring.
     */
    'defaults' => [
        'max_score' => 100,
        'pass_mark' => 40,
        'duration_minutes' => 90,
    ],
];
