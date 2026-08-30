<?php

declare(strict_types=1);

/**
 * Finance & Billing capability — configuration.
 *
 * Permission keys are pulled into the global catalog by the
 * `registered_capabilities` list in config/identity.php.
 */
return [
    'permissions' => [
        ['key' => 'finance.fees.read',        'domain' => 'finance', 'label' => 'View fee structures',   'description' => 'See the fee catalogue for the current academic year.'],
        ['key' => 'finance.fees.write',       'domain' => 'finance', 'label' => 'Manage fee structures', 'description' => 'Create, edit and (de)activate fee lines.'],

        ['key' => 'finance.invoices.read',    'domain' => 'finance', 'label' => 'View invoices',         'description' => 'See invoices issued to students and their line items.'],
        ['key' => 'finance.invoices.write',   'domain' => 'finance', 'label' => 'Draft invoices',        'description' => 'Create and edit draft invoices before they are posted to the ledger.'],
        ['key' => 'finance.invoices.issue',   'domain' => 'finance', 'label' => 'Issue invoices',        'description' => 'Post draft invoices to the ledger — creates Dr AR / Cr Revenue entries.'],
        ['key' => 'finance.invoices.void',    'domain' => 'finance', 'label' => 'Void invoices',         'description' => 'Reverse a posted invoice with a balancing journal entry.'],

        ['key' => 'finance.payments.read',    'domain' => 'finance', 'label' => 'View payments',         'description' => 'See recorded receipts and Paychangu reconciliations.'],
        ['key' => 'finance.payments.write',   'domain' => 'finance', 'label' => 'Record payments',       'description' => 'Log a payment against an outstanding invoice.'],
        ['key' => 'finance.payments.refund',  'domain' => 'finance', 'label' => 'Refund payments',       'description' => 'Reverse a succeeded payment via a refund journal entry.'],

        ['key' => 'finance.reports.read',     'domain' => 'finance', 'label' => 'View finance reports',  'description' => 'See P&L, receivables aging, and collection dashboards.'],
        ['key' => 'finance.ledger.read',      'domain' => 'finance', 'label' => 'View ledger',           'description' => 'Inspect chart of accounts and per-account journal postings.'],
    ],

    /**
     * Amounts are stored as integer minor units (tambala/kobo/cents).
     * Default currency for tenants that haven't set one explicitly.
     */
    'defaults' => [
        'currency' => 'MWK',
        // Paychangu blended fee (0.25%) used only for realistic gateway
        // fee snapshots on manual/imported payments; real settlement
        // fees come off the actual Paychangu webhook payload.
        'paychangu_fee_bps' => 25,
        'invoice_due_days' => 20,
    ],
];
