<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Domains\Finance\Events\PaymentRecorded;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Events\BusinessEvent;
use Illuminate\Support\Facades\DB;

/**
 * The portal user accounts of guardians linked to the student on the
 * payment's invoice — i.e. the people who pay the school (guardians
 * without a portal account are skipped).
 */
final class PaymentGuardianRecipients implements ResolvesNotificationRecipients
{
    public function resolve(BusinessEvent $event): iterable
    {
        if (! $event instanceof PaymentRecorded) {
            return [];
        }

        $invoice = Invoice::query()->find($event->payment->invoice_id);
        if ($invoice === null) {
            return [];
        }

        $userIds = DB::table('student_guardians')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->where('student_guardians.student_id', $invoice->student_id)
            ->whereNotNull('guardians.user_id')
            ->pluck('guardians.user_id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()->whereIn('id', $userIds)->get();
    }
}
