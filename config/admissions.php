<?php

declare(strict_types=1);

/**
 * Admissions capability — configuration.
 *
 * Permission keys extend the Identity catalog. Roles reference these by key
 * (see config/identity.php + SystemRolesSeeder).
 */
return [
    'permissions' => [
        ['key' => 'admissions.applications.read',   'domain' => 'admissions', 'label' => 'View applications',   'description' => 'See applications and pipeline stages.'],
        ['key' => 'admissions.applications.write',  'domain' => 'admissions', 'label' => 'Manage applications', 'description' => 'Create and edit applications, advance stages.'],
        ['key' => 'admissions.offers.write',        'domain' => 'admissions', 'label' => 'Issue offers',        'description' => 'Draft, send, and process responses to offers.'],
        ['key' => 'admissions.enroll',              'domain' => 'admissions', 'label' => 'Enroll applicants',   'description' => 'Convert an accepted applicant into a Student record.'],
    ],

    /**
     * Reference pattern for auto-generated application references.
     * Placeholders: {year} (4-digit academic year start), {seq} (padded seq).
     */
    'reference' => [
        'pattern' => 'APP-{year}-{seq}',
        'sequence_padding' => 5,
    ],

    'offer' => [
        // Default TTL applied when the caller doesn't provide expires_on.
        'default_ttl_days' => 21,
    ],
];
