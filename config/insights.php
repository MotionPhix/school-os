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
        ['key' => 'insights.ai.read',          'domain' => 'insights', 'label' => 'Use the AI assistant',      'description' => 'Ask questions against the tenant\'s authoritative AI context snapshot.'],
    ],

    'defaults' => [
        'period' => 'last_30d',   // matches InsightPeriod enum
        'currency' => 'MWK',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Assistant (opencode Zen)
    |--------------------------------------------------------------------------
    |
    | The School Assistant answers questions from a compact snapshot built
    | by AiContextBuilder. Disabled by default; flip INSIGHTS_AI_ENABLED
    | and set OPENCODE_ZEN_KEY when deploying.
    */
    'ai' => [
        'enabled' => (bool) env('INSIGHTS_AI_ENABLED', false),
        'provider' => 'zen',
        'model' => env('OPENCODE_ZEN_MODEL', 'big-pickle'),
        'timeout' => (int) env('INSIGHTS_AI_TIMEOUT', 60),
        'max_question_length' => 500,
        'rate_limit_per_minute' => (int) env('INSIGHTS_AI_RATE_LIMIT', 15),
    ],
];
