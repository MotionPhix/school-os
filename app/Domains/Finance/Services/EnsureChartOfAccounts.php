<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Enums\AccountKind;
use App\Enums\CurrencyCode;
use App\Models\Account;
use App\Support\TenantContext;

/**
 * Idempotently ensures the standard chart-of-accounts exists for the
 * active tenant and given currency. Called on-demand from ledger
 * services so a fresh tenant doesn't need a bespoke seeder.
 */
final class EnsureChartOfAccounts
{
    public function forCurrency(CurrencyCode $currency, ?string $tenantId = null): void
    {
        $tenantId ??= app(TenantContext::class)->id();
        if ($tenantId === null) {
            return;
        }

        foreach (AccountKind::cases() as $kind) {
            Account::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'kind' => $kind->value,
                    'currency' => $currency->value,
                ],
                [
                    'type' => $kind->type()->value,
                    'name' => $kind->displayName(),
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    public function get(AccountKind $kind, CurrencyCode $currency, ?string $tenantId = null): Account
    {
        $tenantId ??= app(TenantContext::class)->id();
        $this->forCurrency($currency, $tenantId);

        return Account::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind->value)
            ->where('currency', $currency->value)
            ->firstOrFail();
    }
}
