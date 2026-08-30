<?php

declare(strict_types=1);

/**
 * Communications capability — configuration.
 *
 * Permission keys are pulled into the global catalog by the
 * `registered_capabilities` list in config/identity.php.
 */
return [
    'permissions' => [
        ['key' => 'communications.overview.read',        'domain' => 'communications', 'label' => 'View communications overview',  'description' => 'See the communications KPI board (throughput, unread, delivery).'],

        ['key' => 'communications.announcements.read',   'domain' => 'communications', 'label' => 'View announcements',            'description' => 'See announcements sent, scheduled, or drafted for the tenant.'],
        ['key' => 'communications.announcements.write',  'domain' => 'communications', 'label' => 'Draft announcements',           'description' => 'Compose and schedule announcements before send.'],
        ['key' => 'communications.announcements.send',   'domain' => 'communications', 'label' => 'Send announcements',            'description' => 'Dispatch a drafted or scheduled announcement to its audience.'],
        ['key' => 'communications.announcements.archive', 'domain' => 'communications', 'label' => 'Archive announcements',         'description' => 'Retire an announcement so it stops appearing in the active list.'],

        ['key' => 'communications.threads.read',         'domain' => 'communications', 'label' => 'View message threads',          'description' => 'Access staff↔guardian direct-message threads.'],
        ['key' => 'communications.threads.write',        'domain' => 'communications', 'label' => 'Reply to message threads',      'description' => 'Reply, change status, mark read on message threads.'],

        ['key' => 'communications.broadcasts.read',      'domain' => 'communications', 'label' => 'View broadcasts',               'description' => 'See SMS/email campaigns and their delivery stats.'],
        ['key' => 'communications.broadcasts.write',     'domain' => 'communications', 'label' => 'Manage broadcasts',             'description' => 'Draft, edit and schedule SMS/email broadcasts.'],
        ['key' => 'communications.broadcasts.start',     'domain' => 'communications', 'label' => 'Start broadcasts',              'description' => 'Kick off a drafted broadcast and begin delivery.'],
        ['key' => 'communications.broadcasts.cancel',    'domain' => 'communications', 'label' => 'Cancel broadcasts',             'description' => 'Halt an in-flight broadcast.'],
    ],

    /**
     * Fallback recipient estimates when we cannot compute the exact
     * audience server-side (e.g. an ad-hoc custom list before it's
     * materialised). Kept small so a UI hint stays honest.
     */
    'audience_estimates' => [
        'whole_school' => 640,
        'staff' => 48,
        'teachers' => 36,
        'students' => 512,
        'guardians' => 412,
        'class' => 32,
        'custom' => 50,
    ],

    /**
     * Blended SMS cost snapshot in minor units (tambala) used when a
     * broadcast is drafted before the SMS gateway returns a real quote.
     * Real settlement cost overwrites this once the broadcast completes.
     */
    'sms_cost_minor_per_recipient' => 2500, // MWK 25.00
    'currency' => 'MWK',
];
