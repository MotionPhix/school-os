<?php

declare(strict_types=1);

/**
 * Institution capability — configuration.
 *
 * Permission keys extend the Identity catalog. Roles reference these
 * by key (see config/identity.php + SystemRolesSeeder).
 */
return [
    'permissions' => [
        ['key' => 'institution.profile.read',   'domain' => 'institution', 'label' => 'View institution profile', 'description' => 'See institution details, contact and accreditation.'],
        ['key' => 'institution.profile.write',  'domain' => 'institution', 'label' => 'Manage institution profile', 'description' => 'Edit institution details, contact and accreditation.'],
        ['key' => 'institution.campuses.read',  'domain' => 'institution', 'label' => 'View campuses',   'description' => 'See campuses in this institution.'],
        ['key' => 'institution.campuses.write', 'domain' => 'institution', 'label' => 'Manage campuses', 'description' => 'Create, edit and close campuses.'],
        ['key' => 'institution.years.read',     'domain' => 'institution', 'label' => 'View academic years', 'description' => 'See academic years and terms.'],
        ['key' => 'institution.years.write',    'domain' => 'institution', 'label' => 'Manage academic years', 'description' => 'Plan academic years, manage terms and lifecycle.'],
        ['key' => 'institution.calendar.read',  'domain' => 'institution', 'label' => 'View calendar',   'description' => 'See institutional calendar events.'],
        ['key' => 'institution.calendar.write', 'domain' => 'institution', 'label' => 'Manage calendar', 'description' => 'Publish or amend calendar events.'],
    ],
];
