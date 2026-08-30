<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Finance\Events\PaymentRecorded as PaymentRecordedEvent;

/** In-app delivery for PaymentRecorded (see config/notifications.php). */
final class PaymentReceipt extends SchoolNotification
{
    public function __construct(private readonly PaymentRecordedEvent $event) {}

    /** @return array{kind: string, title: string, body: string, href: string} */
    public function toArray(object $notifiable): array
    {
        $payment = $this->event->payment;

        return [
            'kind' => 'finance',
            'title' => "Payment received — {$payment->reference}",
            'body' => sprintf(
                '%s · %s %s',
                $payment->student_name,
                $payment->currency->value,
                number_format($payment->amount_minor / 100, 2),
            ),
            'href' => "/finance/invoices/{$payment->invoice_id}",
        ];
    }
}
