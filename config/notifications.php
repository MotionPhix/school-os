<?php

declare(strict_types=1);

use App\Domains\Assessments\Events\ExamPublished as ExamPublishedEvent;
use App\Domains\Communications\Events\AnnouncementSent as AnnouncementSentEvent;
use App\Domains\Finance\Events\InvoiceIssued as InvoiceIssuedEvent;
use App\Notifications\AnnouncementSent;
use App\Notifications\ExamPublished;
use App\Notifications\InvoiceIssued;
use App\Notifications\Recipients\ExamGuardianRecipients;
use App\Notifications\Recipients\ExamTeacherRecipients;

/**
 * Notification policies (handbook Ch. 35): which business events produce
 * which notifications, and who receives them.
 *
 * `recipients` accepts:
 *   - 'tenant_members'                             → every user with a membership in the event's tenant
 *   - 'permission:<key>'                           → tenant members whose roles carry the permission key
 *   - <ResolverClass::class>                       → any ResolvesNotificationRecipients implementation
 *   - array of the above                           → merged + de-duplicated
 */
return [
    'policies' => [
        AnnouncementSentEvent::class => [
            'notification' => AnnouncementSent::class,
            'recipients' => 'tenant_members',
        ],
        InvoiceIssuedEvent::class => [
            'notification' => InvoiceIssued::class,
            'recipients' => 'permission:finance.invoices.read',
        ],
        ExamPublishedEvent::class => [
            'notification' => ExamPublished::class,
            'recipients' => [
                ExamTeacherRecipients::class,
                ExamGuardianRecipients::class,
            ],
        ],
    ],
];
