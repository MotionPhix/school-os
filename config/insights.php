<?php

declare(strict_types=1);

/**
 * Insights & Reports — capability config (Slice 10).
 *
 * Insights is a read-only capability: it composes rollups from
 * People, Admissions, Academics, Attendance, Assessments, and Finance.
 * There are no writes — permissions gate visibility of dashboards.
 */
return [
    'permissions' => [
        ['key' => 'insights.institution.read', 'domain' => 'insights', 'label' => 'View institution snapshot', 'description' => 'Cross-capability KPIs for the whole institution.'],
        ['key' => 'insights.enrollment.read',  'domain' => 'insights', 'label' => 'View enrollment report',    'description' => 'Applications pipeline and enrollment funnel.'],
        ['key' => 'insights.academic.read',    'domain' => 'insights', 'label' => 'View academic report',      'description' => 'Attendance and assessment outcomes by cohort.'],
        ['key' => 'insights.financial.read',   'domain' => 'insights', 'label' => 'View financial report',     'description' => 'Collections, arrears aging and channel mix.'],
    ],

    'defaults' => [
        'period' => 'last_30d',   // matches InsightPeriod enum
        'currency' => 'MWK',
    ],
];
