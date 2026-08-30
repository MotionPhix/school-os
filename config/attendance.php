<?php

declare(strict_types=1);

/**
 * Attendance capability — configuration.
 *
 * Permission keys extend the Identity catalog (registered via
 * `config/identity.php::registered_capabilities`).
 */
return [
    'permissions' => [
        ['key' => 'attendance.sessions.read',  'domain' => 'attendance', 'label' => 'View attendance sessions', 'description' => 'See taken registers for course sections.'],
        ['key' => 'attendance.sessions.write', 'domain' => 'attendance', 'label' => 'Manage attendance sessions', 'description' => 'Open and submit a daily register for a course section.'],
        ['key' => 'attendance.marks.write',    'domain' => 'attendance', 'label' => 'Mark attendance',           'description' => 'Set per-student status on an open (draft) register.'],
        ['key' => 'attendance.summary.read',   'domain' => 'attendance', 'label' => 'View attendance summary',   'description' => 'See per-student attendance rollups and at-risk lists.'],
    ],

    /**
     * Default late-arrival minutes recorded when a mark flips to `late`
     * without an explicit `minutes_late` value on the request.
     */
    'defaults' => [
        'minutes_late' => 5,
    ],
];
