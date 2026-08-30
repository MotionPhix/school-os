<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\AbstractCapabilityPolicy;

final class InvoicePolicy extends AbstractCapabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'finance.invoices.read');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->has($user, 'finance.invoices.read');
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'finance.invoices.write');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->has($user, 'finance.invoices.write') && $invoice->status === InvoiceStatus::Draft;
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->has($user, 'finance.invoices.issue') && $invoice->status === InvoiceStatus::Draft;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->has($user, 'finance.invoices.void') && $invoice->status !== InvoiceStatus::Void;
    }

    public function remind(User $user, Invoice $invoice): bool
    {
        return $this->has($user, 'finance.invoices.write') && $invoice->status !== InvoiceStatus::Void;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->has($user, 'finance.invoices.write') && $invoice->status === InvoiceStatus::Draft;
    }
}
