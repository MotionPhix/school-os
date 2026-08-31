<?php

declare(strict_types=1);

namespace App\Domains\PlatformBilling\Services;

use App\Models\TenantPaymentProvider;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Store/update the TENANT's own PayChangu credentials (used to RECEIVE
 * payments in the parents portal). Keys are encrypted at rest via the
 * model's `encrypted` casts.
 */
final class SaveTenantPaymentProvider
{
    public function handle(string $secretKey, ?string $publicKey, string $mode): TenantPaymentProvider
    {
        if (trim($secretKey) === '') {
            throw ValidationException::withMessages(['secret_key' => 'The PayChangu secret key is required.']);
        }

        $tenantId = (string) app(TenantContext::class)->id();

        return TenantPaymentProvider::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'provider' => 'paychangu',
                'secret_key' => $secretKey,
                'public_key' => $publicKey,
                'mode' => $mode === 'live' ? 'live' : 'test',
                'is_active' => true,
                'verified_at' => now(),
            ],
        );
    }
}
