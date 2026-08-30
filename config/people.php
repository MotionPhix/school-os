<?php

declare(strict_types=1);

/**
 * People capability — configuration.
 *
 * Permission keys extend the Identity catalog. Roles reference these
 * by key (see config/identity.php + SystemRolesSeeder).
 */
return [
    'permissions' => [
        ['key' => 'people.students.read',   'domain' => 'people', 'label' => 'View students',   'description' => 'See student directory and profiles.'],
        ['key' => 'people.students.write',  'domain' => 'people', 'label' => 'Manage students', 'description' => 'Create, edit and change status of students.'],
        ['key' => 'people.guardians.read',  'domain' => 'people', 'label' => 'View guardians',  'description' => 'See guardians linked to students.'],
        ['key' => 'people.guardians.write', 'domain' => 'people', 'label' => 'Manage guardians', 'description' => 'Create, edit and invite guardians; link them to students.'],
        ['key' => 'people.staff.read',      'domain' => 'people', 'label' => 'View staff',      'description' => 'See staff directory and profiles.'],
        ['key' => 'people.staff.write',     'domain' => 'people', 'label' => 'Manage staff',    'description' => 'Hire, edit and change status of staff members.'],
        ['key' => 'people.documents.read',  'domain' => 'people', 'label' => 'View documents',  'description' => 'See profile documents attached to people.'],
        ['key' => 'people.documents.write', 'domain' => 'people', 'label' => 'Manage documents', 'description' => 'Upload, replace and remove profile documents and avatars.'],
    ],

    /**
     * Presigned upload / storage tuning for avatars + documents.
     * The controller enforces mime + size before persisting.
     */
    'media' => [
        'avatar_max_kb' => 2048,
        'avatar_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
        'document_max_kb' => 10240,
        'document_mimes' => [
            'application/pdf',
            'image/png', 'image/jpeg', 'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'disk' => 'local', // TODO(Slice 9+): swap for a cloud disk when Media capability lands.
    ],

    /**
     * Portal access mapping. People never creates credentials itself — it asks
     * Identity to invite the email with these role keys (see SystemRolesSeeder).
     */
    'guardian_role_key' => 'guardian',
    'staff_default_role_key' => 'teacher',
    'staff_role_keys' => [
        'teaching' => 'teacher',
        'administration' => 'registrar',
        'support' => 'teacher',
        'facilities' => 'teacher',
        'leadership' => 'principal',
    ],
];
