<?php

declare(strict_types=1);

namespace App\Domains\Finance\Support;

use App\Models\Invoice;

/**
 * Human-readable invoice numbers, unique per tenant per year.
 * Pattern: INV-YYYY-NNNNNN. Uses SELECT ... FOR UPDATE inside the
 * calling transaction so concurrent issues don't collide.
 */
final class InvoiceNumberGenerator
{
    public function next(string $tenantId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = sprintf('INV-%d-', $year);

        $last = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $n = $last ? ((int) mb_substr((string) $last, mb_strlen($prefix))) + 1 : 1;

        return $prefix.mb_str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    public function nextPaymentReference(string $gateway): string
    {
        $prefix = $gateway === 'paychangu' ? 'PCH-' : 'MAN-';

        return $prefix.mb_strtoupper(mb_substr(bin2hex(random_bytes(4)), 0, 6));
    }
}
