<?php

declare(strict_types=1);
use App\Notifications\AnnouncementSent;
use App\Notifications\InvoiceIssued;

/**
 * Notification policies (handbook Ch. 35): which business events produce
 * which notifications, and who receives them.
 *
 * `recipients` strategies:
 *   - 'tenant_members'          → every user with a membership in the event's tenant
 *   - 'permission:<key>'        → tenant members whose roles carry the permission key
 */
return [
    'policies' => [
        App\Domains\Communications\Events\AnnouncementSent::class => [
            'notification' => AnnouncementSent::class,
            'recipients' => 'tenant_members',
        ],
        App\Domains\Finance\Events\InvoiceIssued::class => [
            'notification' => InvoiceIssued::class,
            'recipients' => 'permission:finance.invoices.read',
        ],
    ],
];
