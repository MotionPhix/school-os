<?php

declare(strict_types=1);

/**
 * Identity & Access — Permission Catalog.
 *
 * The catalog is the single source of truth for all permission keys the
 * system recognises. Roles reference these by key (stored as JSON on
 * `roles.permission_keys`). Adding a new permission is a config change,
 * not a migration.
 *
 * Structure:
 *   'key'         → dotted, capability-first ("admissions.applications.review")
 *   'domain'      → owning Business Capability
 *   'label'       → short UI label
 *   'description' → shown in Roles UI when composing a role
 *
 * TODO: Slices 2-10 will each add their own block below as capabilities land.
 */
return [
    /**
     * Capabilities whose config files contribute to the permission catalog.
     * Each slice registers its short name here and ships a matching
     * `config/<name>.php` with a `permissions` array.
     */
    'registered_capabilities' => [
        'institution',
        'people',
        'admissions',
        'academics',
        'attendance',
        'assessments',
        'finance',
        'communications',
        'insights',
    ],

    'permissions' => [

        // -------- Identity & Access (Slice 1) --------
        ['key' => 'identity.users.read',       'domain' => 'identity', 'label' => 'View users',      'description' => 'See directory of users in the tenant.'],
        ['key' => 'identity.users.write',      'domain' => 'identity', 'label' => 'Manage users',    'description' => 'Invite, edit, suspend or deactivate users.'],
        ['key' => 'identity.roles.read',       'domain' => 'identity', 'label' => 'View roles',      'description' => 'See roles and their permissions.'],
        ['key' => 'identity.roles.write',      'domain' => 'identity', 'label' => 'Manage roles',    'description' => 'Create, edit and assign roles.'],
        ['key' => 'identity.tenants.read',     'domain' => 'identity', 'label' => 'View tenants',    'description' => 'See tenants this account belongs to.'],
        ['key' => 'identity.tenants.write',    'domain' => 'identity', 'label' => 'Manage tenants',  'description' => 'Create, configure or suspend tenants (platform-level).'],
        ['key' => 'identity.invitations.read', 'domain' => 'identity', 'label' => 'View invites',    'description' => 'See pending, accepted and revoked invitations.'],
        ['key' => 'identity.invitations.write', 'domain' => 'identity', 'label' => 'Manage invites',  'description' => 'Issue, resend and revoke invitations.'],
        ['key' => 'platform.trash.restore', 'domain' => 'platform', 'label' => 'Restore archived records', 'description' => 'Restore soft-deleted records from the trash (tenant-scoped).'],
        ['key' => 'platform.observability.alert', 'domain' => 'platform', 'label' => 'Receive operational alerts', 'description' => 'In-app alerts for operational failures (e.g. broadcast delivery failures).'],
    ],

    /**
     * System roles seeded on install. `permission_keys: '*'` grants every
     * key currently in the catalog. Keys not yet in the catalog (future
     * slices) are silently skipped at seed time.
     */
    'system_roles' => [
        [
            'key' => 'platform.admin',
            'name' => 'Platform Administrator',
            'description' => 'Full access across all tenants. Reserved for SchoolOS staff.',
            'scope' => 'platform',
            'permission_keys' => '*',
        ],
        [
            'key' => 'principal',
            'name' => 'Principal',
            'description' => 'Head of institution. Full operational visibility, cross-domain approvals.',
            'scope' => 'tenant',
            'permission_keys' => [
                'identity.users.read',
                'identity.roles.read',
                'identity.invitations.read',
                // Slice 2 — Institution
                'institution.profile.read',
                'institution.profile.write',
                'institution.campuses.read',
                'institution.campuses.write',
                'institution.years.read',
                'institution.years.write',
                'institution.calendar.read',
                'institution.calendar.write',
                // Slice 3 — People
                'people.students.read',
                'people.students.write',
                'people.guardians.read',
                'people.guardians.write',
                'people.staff.read',
                'people.staff.write',
                'people.documents.read',
                'people.documents.write',
                // Slice 4 — Admissions
                'admissions.applications.read',
                'admissions.applications.write',
                'admissions.offers.write',
                'admissions.enroll',
                // Slice 5 — Academics
                'academics.subjects.read',
                'academics.subjects.write',
                'academics.courses.read',
                'academics.courses.write',
                'academics.timetable.read',
                'academics.timetable.write',
                'academics.gradebook.read',
                'academics.gradebook.write',
                // Slice 6 — Attendance
                'attendance.sessions.read',
                'attendance.sessions.write',
                'attendance.marks.write',
                'attendance.summary.read',
                // Slice 7 — Assessments & Exams
                'assessments.periods.read',
                'assessments.periods.write',
                'assessments.exams.read',
                'assessments.exams.write',
                'assessments.results.write',
                'assessments.publish',
                'assessments.reports.read',
                // Slice 8 — Finance & Billing (Principal oversees, doesn't record)
                'finance.fees.read',
                'finance.invoices.read',
                'finance.invoices.void',
                'finance.payments.read',
                'finance.reports.read',
                'finance.ledger.read',
                // Slice 9 — Communications (Principal drives school-wide messaging)
                'communications.overview.read',
                'communications.announcements.read',
                'communications.announcements.write',
                'communications.announcements.send',
                'communications.announcements.archive',
                'communications.threads.read',
                'communications.threads.write',
                'communications.broadcasts.read',
                'communications.broadcasts.write',
                'communications.broadcasts.start',
                'communications.broadcasts.cancel',
                // Slice 10 — Insights & Reports (Principal sees everything)
                'insights.institution.read',
                'insights.enrollment.read',
                'insights.academic.read',
                'insights.financial.read',
                'insights.ai.read',
                // Admin & Trash (Principal restores archived records)
                'platform.trash.restore',
                // Observability (Principal receives operational alerts)
                'platform.observability.alert',
            ],

        ],
        [
            'key' => 'registrar',
            'name' => 'Registrar',
            'description' => 'Owns admissions pipeline and student records.',
            'scope' => 'tenant',
            'permission_keys' => [
                'identity.users.read',
                'institution.profile.read',
                'institution.campuses.read',
                'institution.years.read',
                'institution.calendar.read',
                // Slice 3 — People
                'people.students.read',
                'people.students.write',
                'people.guardians.read',
                'people.guardians.write',
                'people.staff.read',
                'people.documents.read',
                'people.documents.write',
                // Slice 4 — Admissions
                'admissions.applications.read',
                'admissions.applications.write',
                'admissions.offers.write',
                'admissions.enroll',
                // Slice 5 — Academics
                'academics.subjects.read',
                'academics.courses.read',
                'academics.courses.write',
                'academics.timetable.read',
                'academics.timetable.write',
                // Slice 6 — Attendance
                'attendance.sessions.read',
                'attendance.summary.read',
                // Slice 7 — Assessments & Exams (Registrar owns scheduling, not publishing)
                'assessments.periods.read',
                'assessments.periods.write',
                'assessments.exams.read',
                'assessments.exams.write',
                'assessments.reports.read',
                // Slice 9 — Communications (Registrar handles admissions & academic notices)
                'communications.overview.read',
                'communications.announcements.read',
                'communications.announcements.write',
                'communications.announcements.send',
                'communications.threads.read',
                'communications.threads.write',
                'communications.broadcasts.read',
                'communications.broadcasts.write',
                // Slice 10 — Insights (Registrar sees enrollment + academic)
                'insights.institution.read',
                'insights.enrollment.read',
                'insights.academic.read',
            ],

        ],
        [
            'key' => 'bursar',
            'name' => 'Bursar',
            'description' => 'Manages fees, invoices, and Paychangu reconciliation.',
            'scope' => 'tenant',
            'permission_keys' => [
                'institution.profile.read',
                'institution.years.read',
                'people.students.read',
                'people.guardians.read',
                // Slice 8 — Finance & Billing (Bursar is the day-to-day owner)
                'finance.fees.read',
                'finance.fees.write',
                'finance.invoices.read',
                'finance.invoices.write',
                'finance.invoices.issue',
                'finance.invoices.void',
                'finance.payments.read',
                'finance.payments.write',
                'finance.payments.refund',
                'finance.reports.read',
                'finance.ledger.read',
                // Slice 9 — Communications (Bursar sends fee notices & runs SMS broadcasts)
                'communications.overview.read',
                'communications.announcements.read',
                'communications.announcements.write',
                'communications.announcements.send',
                'communications.threads.read',
                'communications.threads.write',
                'communications.broadcasts.read',
                'communications.broadcasts.write',
                'communications.broadcasts.start',
                'communications.broadcasts.cancel',
                // Slice 10 — Insights (Bursar sees financial dashboards)
                'insights.institution.read',
                'insights.financial.read',
                'insights.ai.read',
            ],

        ],
        [
            'key' => 'teacher',
            'name' => 'Teacher',
            'description' => 'Class-level access to rosters, attendance, and assessments.',
            'scope' => 'campus', // TODO(Slice 2 follow-up): resolve against `campuses`
            'permission_keys' => [
                'institution.calendar.read',
                'institution.years.read',
                'people.students.read',
                'people.guardians.read',
                'people.staff.read',
                // Slice 5 — Academics (class-level reads + own gradebook)
                'academics.subjects.read',
                'academics.courses.read',
                'academics.timetable.read',
                'academics.gradebook.read',
                'academics.gradebook.write',
                // Slice 6 — Attendance (own class registers)
                'attendance.sessions.read',
                'attendance.sessions.write',
                'attendance.marks.write',
                'attendance.summary.read',
                // Slice 7 — Assessments & Exams (own class marking + publish)
                'assessments.periods.read',
                'assessments.exams.read',
                'assessments.exams.write',
                'assessments.results.write',
                'assessments.publish',
                'assessments.reports.read',
                // Slice 9 — Communications (class-level DMs and class announcements)
                'communications.overview.read',
                'communications.announcements.read',
                'communications.announcements.write',
                'communications.threads.read',
                'communications.threads.write',
            ],

        ],
        [
            'key' => 'guardian',
            'name' => 'Guardian (Portal)',
            'description' => 'Parent/guardian portal access: own children only.',
            'scope' => 'guardian', // resolved against guardian_student links
            'permission_keys' => [
                'institution.calendar.read',
                'people.students.read',
                'attendance.summary.read',
                'assessments.reports.read',
                'finance.invoices.read',
                'finance.payments.read',
                'communications.announcements.read',
                'communications.threads.read',
                'communications.threads.write',
            ],
        ],
        [
            'key' => 'it.admin',
            'name' => 'IT Administrator',
            'description' => 'Manages users, roles, and integrations at tenant level.',
            'scope' => 'tenant',
            'permission_keys' => [
                'identity.users.read',
                'identity.users.write',
                'identity.roles.read',
                'identity.roles.write',
                'identity.invitations.read',
                'identity.invitations.write',
                'institution.profile.read',
                'institution.campuses.read',
                'institution.years.read',
                'institution.calendar.read',
                'people.staff.read',
                'people.staff.write',
            ],
        ],
    ],

    'invitation' => [
        'ttl_days' => 14,
    ],
];
