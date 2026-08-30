<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Finance\Events\InvoiceIssued as InvoiceIssuedEvent;

/** In-app delivery for InvoiceIssued (see config/notifications.php). */
final class InvoiceIssued extends SchoolNotification
{
    public function __construct(private readonly InvoiceIssuedEvent $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $invoice = $this->event->invoice;

        return [
            'kind' => 'finance',
            'title' => "Invoice {$invoice->number} issued",
            'body' => sprintf(
                '%s · %s %s',
                $invoice->student_name,
                $invoice->currency->value,
                number_format($invoice->total_minor / 100, 2),
            ),
            'href' => "/finance/invoices/{$invoice->id}",
        ];
    }
}
